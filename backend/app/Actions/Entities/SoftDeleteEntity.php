<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\OpenEntityRequest;
use App\Data\Entities\SoftDeletionInstruction;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntitySoftDeleted;
use App\Exceptions\Entities\EntityRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityDeadlineFreezer;
use App\Services\Entities\OpenRequestDirectory;
use App\Services\Identity\UserSessionTerminator;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class SoftDeleteEntity
{
    public function __construct(
        private OpenRequestDirectory $openRequests,
        private EntityDeadlineFreezer $deadlines,
        private RequestContext $context,
        private UserSessionTerminator $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Entity $entity, SoftDeletionInstruction $instruction): Entity
    {
        if ($entity->trashed()) {
            throw IllegalStateTransition::from('deleted', 'delete');
        }

        $open = $this->openRequests->openRequestsOf($entity);
        $frozenNumbers = array_map(static fn (OpenEntityRequest $request): string => $request->number, $open);

        if (! $instruction->acknowledges($frozenNumbers)) {
            throw EntityRejected::openRequestsConfirmationRequired($open);
        }

        $this->context->recordReason($instruction->reason);

        DB::transaction(function () use ($actor, $entity, $instruction, $frozenNumbers): void {
            $entity->forceFill(['deleted_by_user_id' => $actor->id, 'deletion_reason' => $instruction->reason])->save();
            $entity->delete();
            $pausedDeadlines = $this->deadlines->pause($entity);

            $owner = $entity->owner()->first();

            if ($owner instanceof User) {
                $this->sessions->terminateAll($owner);
            }

            $this->audit->record(new AuditEntry(
                AuditEvent::EntitySoftDeleted,
                $entity,
                ['deleted' => false],
                ['deleted' => true, 'frozen_request_numbers' => $frozenNumbers, 'paused_deadlines' => $pausedDeadlines],
            ));

            EntitySoftDeleted::dispatch($entity, $frozenNumbers, $actor->id);
        });

        return $entity;
    }
}
