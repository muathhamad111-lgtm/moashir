<?php

declare(strict_types=1);

namespace App\Contracts\Criteria;

use App\Models\Criteria\CriteriaVersion;

interface OpenAssessmentCounter
{
    public function countBoundTo(CriteriaVersion $version): int;
}
