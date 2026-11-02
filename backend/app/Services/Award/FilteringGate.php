<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\StaffActionReason;
use App\Services\Entities\EntityFreezeGuard;

final readonly class FilteringGate
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private StaffActionReason $reason,
        private AssessmentRowLock $rowLock,
        private AssessmentStateMachine $stateMachine,
        private CycleSegregation $segregation,
    ) {}

    public function enter(User $actor, AwardCycle $cycle, Assessment $assessment, AdvancedAssessmentStatus $next, AssessmentVerb $verb, ?string $reason): Assessment
    {
        $this->freezeGuard->assertNotFrozen($assessment->entity);
        $this->reason->record($actor, $reason);
        $lockedCycle = AwardCycle::query()->whereKey($cycle->id)->sharedLock()->firstOrFail();

        if (! $lockedCycle->status->allowsFilteringDecisions()) {
            throw AwardRejected::filteringNotOpen();
        }

        $locked = $this->rowLock->lock($assessment->id);
        $this->stateMachine->assertAllows($locked, $next, $verb);

        if (! $actor->isSuperAdmin()) {
            $this->segregation->assertMayDecideOn($actor, $locked);
        }

        if (! $locked->currentResult instanceof AssessmentResultSnapshot) {
            throw AssessmentRejected::resultRequired();
        }

        return $locked->setRelation('awardCycle', $lockedCycle);
    }
}
