<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentCompletionRequest;

final readonly class SubmissionForm
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    public function __construct(
        public CvSheet $cv,
        public array $criteria,
        public EvidenceListing $evidence,
        public SubmissionGaps $gaps,
        public ?AssessmentCompletionRequest $completionRequest,
        public UploadLimits $uploadLimits,
        public ?CriteriaMoveSummary $criteriaUpdate,
    ) {}
}
