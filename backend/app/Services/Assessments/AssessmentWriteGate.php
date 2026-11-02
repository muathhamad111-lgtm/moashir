<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\AssessmentState;
use App\Enums\Assessments\AssessmentVerb;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;

final readonly class AssessmentWriteGate
{
    public function __construct(
        private AssessmentEntityGuard $entityGuard,
        private AssessmentRowLock $rowLock,
        private AssessmentOverrideReason $overrideReason,
        private AssessmentStateMachine $stateMachine,
    ) {}

    public function enter(User $actor, Assessment $assessment, AssessmentState $next, AssessmentVerb $verb, ?string $reason): Assessment
    {
        $this->entityGuard->assertMayAct($assessment->entity);

        $locked = $this->rowLock->lock($assessment->id);
        $this->overrideReason->requireFor($actor, $locked, $reason);
        $this->stateMachine->assertAllows($locked, $next, $verb);

        return $locked;
    }
}
