<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityProfileChangeSet;
use App\Data\Entities\ProfileChangeDecision;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\ProfileChangeStatus;
use App\Enums\Entities\ProfileChangeVerdict;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\ProfileChanges\ProfileChangeApplier;
use App\Services\Entities\ProfileChanges\ProfileChangeDecisionGuard;
use App\Services\Entities\ProfileChanges\ProfileChangeNotifier;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects each item of an entity's profile change request: approved fields and files apply to the
 * entity's profile, the rest are rejected for the reviewer's reason.
 */
final readonly class DecideEntityProfileChange
{
    public function __construct(
        private ProfileChangeDecisionGuard $guard,
        private ProfileChangeApplier $applier,
        private AuditRecorder $audit,
        private ProfileChangeNotifier $notifier,
        private RequestContext $context,
    ) {}

    public function handle(User $reviewer, EntityProfileChangeRequest $request, ProfileChangeDecision $decision): EntityProfileChangeRequest
    {
        $this->context->recordReason($decision->reason);

        DB::transaction(function () use ($reviewer, $request, $decision): void {
            $locked = EntityProfileChangeRequest::query()->with(['submitter', 'pendingLogo', 'pendingVatCertificate'])->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->guard->assertDecidable($locked, $decision);

            $entity = $this->lockedEntity($locked->entity_id);
            $this->guard->assertApplicable($locked, $entity, $decision);

            $changes = $this->applier->apply($locked, $entity, $decision);
            $this->recordDecision($locked, $reviewer, $decision);

            $this->record($locked->setRelation('entity', $entity), $changes);
        });

        $this->notifier->toEntity($request->refresh()->load('entity'));

        return $request;
    }

    private function lockedEntity(int $entityId): Entity
    {
        $entity = Entity::query()->whereKey($entityId)->lockForUpdate()->firstOrFail();

        return $entity->setRelation('owner', User::query()->where('entity_id', $entity->id)->lockForUpdate()->first());
    }

    private function recordDecision(EntityProfileChangeRequest $request, User $reviewer, ProfileChangeDecision $decision): void
    {
        $verdicts = $decision->verdictsFor($request->items());
        $approved = count(ProfileChangeVerdict::Approved->itemsIn($verdicts));

        $request->forceFill([
            'status' => ProfileChangeStatus::decidedFrom($approved, count($verdicts) - $approved),
            'decisions' => $verdicts,
            'reviewed_by_user_id' => $reviewer->id,
            'review_note' => $decision->rejectsAnyOf($request->items()) ? $decision->reason : null,
            'decided_at' => Date::now(),
        ])->save();
    }

    private function record(EntityProfileChangeRequest $request, EntityProfileChangeSet $changes): void
    {
        $audited = $changes->withoutPersonalData();

        $this->audit->record(new AuditEntry(AuditEvent::EntityProfileChangeDecided, $request->entity, $audited->before, [
            ...$audited->after,
            'request' => $request->public_id,
            'status' => $request->status->value,
            'approved' => ProfileChangeVerdict::Approved->itemsIn($request->decisions ?? []),
            'rejected' => ProfileChangeVerdict::Rejected->itemsIn($request->decisions ?? []),
        ]));
    }
}
