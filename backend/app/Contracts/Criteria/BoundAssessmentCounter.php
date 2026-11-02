<?php

declare(strict_types=1);

namespace App\Contracts\Criteria;

use App\Data\Criteria\BoundAssessmentCount;

interface BoundAssessmentCounter
{
    /**
     * @param  list<int>  $versionIds
     * @return array<int, BoundAssessmentCount>
     */
    public function countsFor(array $versionIds): array;
}
