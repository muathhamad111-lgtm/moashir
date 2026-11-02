<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ReviewMode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

final readonly class AssessorReview
{
    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<Assessment>  $context
     */
    public function __construct(
        public ReviewMode $mode,
        public array $criteria,
        public AssessmentScores $scores,
        public CvSheet $cv,
        public EvidenceListing $evidence,
        public FeedbackSheet $feedback,
        public ?AssessmentCompletionRequest $completionRequest,
        public array $context,
    ) {}
}
