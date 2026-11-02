<?php

declare(strict_types=1);

namespace App\Support\Criteria;

use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\Weight;

final class ActiveWeights
{
    /**
     * @param  iterable<MainCriterion|SubCriterion|GuidingPoint>  $nodes
     */
    public static function total(iterable $nodes, ?int $exceptId = null): Weight
    {
        $weights = [];

        foreach ($nodes as $node) {
            if ($node->is_active && $node->id !== $exceptId) {
                $weights[] = $node->weight;
            }
        }

        return Weight::sum($weights);
    }
}
