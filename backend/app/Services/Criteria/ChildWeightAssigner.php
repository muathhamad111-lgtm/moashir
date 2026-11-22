<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\WeightAssignment;
use App\Exceptions\Criteria\CriteriaTreeRejected;
use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Collection;

/**
 * Checks a set of new weights for the children of one criterion as a whole: every child belongs to the
 * parent and the active children, with their new weights, stay within the parent weight.
 */
final class ChildWeightAssigner
{
    /**
     * @param  Collection<int, SubCriterion>|Collection<int, GuidingPoint>  $children
     * @param  list<WeightAssignment>  $assignments
     * @return list<WeightAssignment> the assignments that change a weight
     */
    public function changes(Collection $children, Weight $capacity, array $assignments): array
    {
        $byId = $children->keyBy('id');
        $planned = [];

        foreach ($assignments as $index => $assignment) {
            if (! $byId->has($assignment->nodeId)) {
                throw CriteriaTreeRejected::notAChild($index);
            }

            $planned[$assignment->nodeId] = $assignment;
        }

        $this->assertWithinParent($children, $capacity, $planned);

        return array_values(array_filter(
            $planned,
            static fn (WeightAssignment $assignment): bool => ! $byId->get($assignment->nodeId)?->weight?->equals($assignment->weight),
        ));
    }

    /**
     * @param  Collection<int, SubCriterion>|Collection<int, GuidingPoint>  $children
     * @param  array<int, WeightAssignment>  $planned
     */
    private function assertWithinParent(Collection $children, Weight $capacity, array $planned): void
    {
        $weights = [];

        foreach ($children as $child) {
            if ($child->is_active) {
                $weights[] = isset($planned[$child->id]) ? $planned[$child->id]->weight : ($child->weight ?? Weight::zero());
            }
        }

        $total = Weight::sum($weights);

        if ($total->isGreaterThan($capacity)) {
            throw CriteriaWeightRejected::childrenExceedParent($total, $capacity);
        }
    }
}
