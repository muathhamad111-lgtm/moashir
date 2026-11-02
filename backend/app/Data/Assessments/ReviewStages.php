<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Enums\Assessments\CompletionExpiryOutcome;

final readonly class ReviewStages
{
    public function __construct(
        public ReviewedAssessmentState $awaitingAssignment,
        public ReviewedAssessmentState $assigned,
        public ReviewedAssessmentState $underReview,
        public ReviewedAssessmentState $reviewed,
        public ReviewedAssessmentState $needsCompletion,
        public ReviewedAssessmentState $afterCompletion,
        public ReviewedAssessmentState $declinedIncomplete,
    ) {}

    public function afterCompletionExpiry(CompletionExpiryOutcome $outcome): ReviewedAssessmentState
    {
        return match ($outcome) {
            CompletionExpiryOutcome::ContinueAsSubmitted => $this->afterCompletion,
            CompletionExpiryOutcome::Decline => $this->declinedIncomplete,
        };
    }
}
