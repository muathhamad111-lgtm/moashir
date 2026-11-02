<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final readonly class EntityContentGate
{
    public function __construct(
        private AssessmentEntityGuard $entityGuard,
        private AssessmentRowLock $rowLock,
        private AssessmentOverrideReason $overrideReason,
    ) {}

    public function enter(User $actor, Assessment $assessment, ?string $reason): Assessment
    {
        $this->assertOpenFor($assessment);

        $locked = $this->rowLock->lock($assessment->id);
        $this->overrideReason->requireFor($actor, $locked, $reason);
        $this->assertOpenFor($locked);

        return $locked;
    }

    public function assertOpenFor(Assessment $assessment): void
    {
        $this->entityGuard->assertMayAct($assessment->entity);

        if (! $assessment->status instanceof ReviewedAssessmentState || ! $assessment->status->acceptsEntityContent()) {
            throw IllegalStateTransition::from((string) $assessment->status->value, AssessmentVerb::SaveDraft->value);
        }

        $this->assertSubmissionWindowOpen($assessment);
        $this->assertCompletionDeadlineOpen($assessment);
    }

    private function assertSubmissionWindowOpen(Assessment $assessment): void
    {
        if ($assessment->status !== AdvancedAssessmentStatus::Draft) {
            return;
        }

        $cycle = $assessment->loadMissing('awardCycle.regions')->awardCycle;

        if (! $cycle instanceof AwardCycle || ! $cycle->acceptsApplicationsAt(Date::now())) {
            throw AwardRejected::submissionClosed();
        }
    }

    private function assertCompletionDeadlineOpen(Assessment $assessment): void
    {
        if ($assessment->status !== $assessment->path->reviewStages()->needsCompletion) {
            return;
        }

        if ($assessment->loadMissing('openCompletionRequest')->openCompletionRequest?->hasExpired(Date::now()) === true) {
            throw AssessmentRejected::completionDeadlinePassed();
        }
    }
}
