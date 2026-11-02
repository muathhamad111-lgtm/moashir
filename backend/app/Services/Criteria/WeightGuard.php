<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\WeightBudget;
use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Support\ValueObjects\Weight;

final class WeightGuard
{
    public function remaining(Weight $capacity, Weight $allocatedToOthers): Weight
    {
        $remaining = $capacity->minus($allocatedToOthers);

        return $remaining->isPositive() ? $remaining : Weight::zero();
    }

    public function assertFits(Weight $capacity, Weight $allocatedToOthers, Weight $requested): void
    {
        $remaining = $this->remaining($capacity, $allocatedToOthers);

        if ($requested->isGreaterThan($remaining)) {
            throw CriteriaWeightRejected::exceedsRemaining($remaining);
        }
    }

    public function assertCovers(Weight $capacity, Weight $allocated): void
    {
        if ($capacity->isLessThan($allocated)) {
            throw CriteriaWeightRejected::belowAllocated($allocated);
        }
    }

    public function childWeight(WeightBudget $budget, ?Weight $requested, bool $isActive): Weight
    {
        if ($budget->distributesEqually) {
            return $requested ?? Weight::fromHundredths(1);
        }

        $weight = $requested ?? throw CriteriaWeightRejected::required();

        if ($isActive) {
            $this->assertFits($budget->capacity, $budget->allocatedToOthers, $weight);
        }

        return $weight;
    }
}
