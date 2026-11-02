<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\AssessmentState;
use App\Enums\Assessments\AssessmentVerb;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;

final readonly class ReviewWriteGate
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private AssessmentRowLock $rowLock,
        private StaffActionReason $reason,
        private AssessmentStateMachine $stateMachine,
    ) {}

    public function enter(User $actor, Assessment $assessment, AssessmentState $next, AssessmentVerb $verb, ?string $reason): Assessment
    {
        $this->freezeGuard->assertNotFrozen($assessment->entity);

        $locked = $this->rowLock->lock($assessment->id);
        $this->reason->record($actor, $reason);
        $this->stateMachine->assertAllows($locked, $next, $verb);

        return $locked;
    }
}
