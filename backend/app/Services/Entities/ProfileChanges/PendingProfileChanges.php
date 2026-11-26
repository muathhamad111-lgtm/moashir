<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\ProfileChangeStatus;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps one pending change request per entity: opens it for the entity's next edit and stores, amends or withdraws it.
 */
final readonly class PendingProfileChanges
{
    public function __construct(
        private AuditRecorder $audit,
        private ProfileChangeNotifier $notifier,
        private ProfileChangeFileDiscarder $files,
    ) {}

    /**
     * The entity's pending request, locked, or a new one when it has none. The caller holds the entity's row lock.
     */
    public function openFor(Entity $entity, User $owner): EntityProfileChangeRequest
    {
        $pending = EntityProfileChangeRequest::query()
            ->where('entity_id', $entity->id)
            ->where('status', ProfileChangeStatus::Pending)
            ->lockForUpdate()
            ->first();

        if ($pending instanceof EntityProfileChangeRequest) {
            return $pending->setRelation('entity', $entity);
        }

        $request = new EntityProfileChangeRequest(['entity_id' => $entity->id, 'submitted_by_user_id' => $owner->id, 'status' => ProfileChangeStatus::Pending]);
        $request->proposeFields([], [], []);

        return $request->setRelation('entity', $entity);
    }

    /**
     * Saves the request after an edit; a request left with no change is withdrawn instead.
     */
    public function store(EntityProfileChangeRequest $request, User $owner): void
    {
        if ($request->isEmpty()) {
            $this->cancel($request, $owner);

            return;
        }

        $isNew = ! $request->exists;
        $request->revision = $isNew ? 1 : $request->revision + 1;
        $request->submitted_by_user_id = $owner->id;
        $request->save();

        $this->audit->record(new AuditEntry(AuditEvent::EntityProfileChangeSubmitted, $request->entity, [], [
            'request' => $request->public_id,
            'revision' => $request->revision,
            'items' => $request->items(),
        ]));

        if ($isNew) {
            DB::afterCommit(fn () => $this->notifier->toApprovers($request));
        }
    }

    /**
     * Withdraws the request and its pending files; the approved profile stays as it is.
     */
    public function cancel(EntityProfileChangeRequest $request, User $owner): void
    {
        if (! $request->exists) {
            return;
        }

        $this->files->discard($request->fileChanges()->pendingIds());
        $request->forceFill(['status' => ProfileChangeStatus::Cancelled, 'submitted_by_user_id' => $owner->id])->save();

        $this->audit->record(new AuditEntry(
            AuditEvent::EntityProfileChangeCancelled,
            $request->entity,
            ['status' => ProfileChangeStatus::Pending->value],
            ['status' => ProfileChangeStatus::Cancelled->value, 'request' => $request->public_id],
        ));
    }
}
