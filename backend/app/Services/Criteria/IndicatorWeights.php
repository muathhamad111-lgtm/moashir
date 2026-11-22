<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\SubCriterion;

/**
 * The indicator weights of one sub-criterion when its evaluation method switches (operator, 2026-11-22). The
 * indicators always stay; only their weights follow the method.
 */
final readonly class IndicatorWeights
{
    public function __construct(private EqualWeightDistributor $distributor, private WeightRebalancer $rebalancer) {}

    /**
     * Evaluated directly: the indicators are descriptive and carry no weight.
     */
    public function clear(SubCriterion $sub): void
    {
        $sub->guidingPoints()->whereNotNull('weight')->update(['weight' => null]);
    }

    /**
     * Scored on indicators again: the sub-criterion's weight is split equally over its indicators and the split is
     * kept equal from now on, until the criteria manager switches it to manual. An inactive indicator gets an even
     * share of the whole set as its stored weight, so it can be activated later.
     */
    public function distributeEqually(SubCriterion $sub): void
    {
        $points = $sub->guidingPoints()->get();

        if ($points->isEmpty()) {
            return;
        }

        $shares = $this->distributor->shares($sub->weight, $points->count());

        foreach ($points->values() as $index => $point) {
            $point->weight = $shares[$index];
            $point->save();
        }

        $sub->distribute_equally = true;
        $sub->save();
        $this->rebalancer->rebalancePointsOf($sub);
    }
}
