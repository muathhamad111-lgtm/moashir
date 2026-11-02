<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Models\Criteria\CriteriaVersion;

final readonly class CriteriaVersionSummary
{
    public function __construct(public CriteriaVersion $version, public BoundAssessmentCount $boundAssessments) {}
}
