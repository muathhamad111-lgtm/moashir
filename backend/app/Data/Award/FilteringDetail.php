<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Data\Assessments\AssessmentResultView;
use App\Data\Assessments\AssessorReview;
use App\Models\Award\FilteringDecision;
use App\Models\Identity\User;

final readonly class FilteringDetail
{
    /**
     * @param  list<FilteringDecision>  $decisions
     */
    public function __construct(
        public AssessorReview $review,
        public AssessmentResultView $result,
        public ?User $assessor,
        public array $decisions,
    ) {}
}
