<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Collection;

final readonly class WeightRebalancer
{
    public function __construct(private EqualWeightDistributor $distributor) {}

    public function rebalanceSubsOf(MainCriterion $main): void
    {
        if (! $main->distribute_equally) {
            return;
        }

        $subs = $main->subCriteria()->where('is_active', true)->get();
        $this->assignShares($subs, $main->weight);

        foreach ($subs as $sub) {
            $this->rebalancePointsOf($sub);
        }
    }

    public function rebalancePointsOf(SubCriterion $sub): void
    {
        if (! $sub->distribute_equally || ! $sub->scoresGuidingPoints()) {
            return;
        }

        $this->assignShares($sub->guidingPoints()->where('is_active', true)->get(), $sub->weight);
    }

    /**
     * @param  Collection<int, SubCriterion>|Collection<int, GuidingPoint>  $nodes
     */
    private function assignShares(Collection $nodes, Weight $total): void
    {
        $shares = $this->distributor->shares($total, $nodes->count());

        foreach ($nodes->values() as $index => $node) {
            if (! $node->weight->equals($shares[$index])) {
                $node->weight = $shares[$index];
                $node->save();
            }
        }
    }
}
