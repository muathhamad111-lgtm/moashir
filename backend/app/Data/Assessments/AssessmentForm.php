<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class AssessmentForm
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    public function __construct(public array $criteria, public AssessmentScores $scores, public ?CriteriaMoveSummary $criteriaUpdate) {}
}
