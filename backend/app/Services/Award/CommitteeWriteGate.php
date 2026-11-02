<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Exceptions\Award\CommitteeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\StaffActionReason;
use App\Services\Entities\EntityFreezeGuard;

final readonly class CommitteeWriteGate
{
    public function __construct(
        private StaffActionReason $reason,
        private EntityFreezeGuard $freezeGuard,
        private AssessmentRowLock $rowLock,
        private CycleSegregation $segregation,
    ) {}

    public function enter(User $actor, CommitteeAssignment $assignment, string $verb, ?string $reason): CommitteeAssignment
    {
        $this->reason->record($actor, $reason);
        $cycle = AwardCycle::query()->whereKey($assignment->award_cycle_id)->sharedLock()->firstOrFail();
        $assessment = $this->rowLock->lock($assignment->assessment_id);
        $locked = CommitteeAssignment::query()->with('member')->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
        $this->freezeGuard->assertNotFrozen($assessment->entity);

        if (! $cycle->status->isCommitteeStage()) {
            throw CommitteeRejected::notStarted();
        }

        $status = $assessment->status;

        if (! $status instanceof AdvancedAssessmentStatus || ! $status->isInCommittee()) {
            throw IllegalStateTransition::from((string) $status->value, $verb);
        }

        $this->assertMayWork($actor, $locked->member, $cycle);

        if (! $locked->status->acceptsWork()) {
            throw CommitteeRejected::evaluationLocked();
        }

        return $locked->setRelation('assessment', $assessment->setRelation('awardCycle', $cycle))->setRelation('awardCycle', $cycle);
    }

    private function assertMayWork(User $actor, CycleCommitteeAssessor $member, AwardCycle $cycle): void
    {
        if (! $member->status->canWork()) {
            throw CommitteeRejected::membershipInactive();
        }

        if (! $actor->isSuperAdmin()) {
            $this->segregation->assertMayActOnCommittee($cycle->id, $member->assessor_user_id);
        }
    }
}
