<?php

declare(strict_types=1);

namespace App\Contracts\Criteria;

use App\Data\Criteria\CycleBindingSummary;
use App\Models\Criteria\CriteriaVersion;

interface CycleCriteriaBindings
{
    public function summary(): CycleBindingSummary;

    /**
     * @return list<int>
     */
    public function moveDrafts(CriteriaVersion $to): array;
}
