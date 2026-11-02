<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentCriteriaMove;

final readonly class CriteriaMoveSummary
{
    /**
     * @param  array<int, int>  $versionNumbers
     * @param  list<CriteriaMoveItem>  $kept
     * @param  list<CriteriaMoveItem>  $reset
     * @param  list<CriteriaMoveItem>  $added
     */
    public function __construct(
        public AssessmentCriteriaMove $move,
        public array $versionNumbers,
        public array $kept,
        public array $reset,
        public array $added,
    ) {}

    public function fromVersionNumber(): ?int
    {
        return $this->versionNumbers[$this->move->from_version_id] ?? null;
    }

    public function toVersionNumber(): ?int
    {
        return $this->versionNumbers[$this->move->to_version_id] ?? null;
    }
}
