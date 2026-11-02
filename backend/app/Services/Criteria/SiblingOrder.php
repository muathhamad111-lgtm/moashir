<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Exceptions\Criteria\CriteriaTreeRejected;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SiblingOrder
{
    /**
     * @param  HasMany<MainCriterion, *>|HasMany<SubCriterion, *>|HasMany<GuidingPoint, *>  $siblings
     */
    public function nextPosition(HasMany $siblings): int
    {
        $highest = $siblings->max('sort_order');

        return (is_numeric($highest) ? (int) $highest : 0) + 1;
    }

    /**
     * @param  Collection<int, MainCriterion>|Collection<int, SubCriterion>|Collection<int, GuidingPoint>  $siblings
     * @param  list<int>  $orderedIds
     */
    public function apply(Collection $siblings, array $orderedIds): void
    {
        $this->assertPermutation($siblings->modelKeys(), $orderedIds);
        $positions = array_flip($orderedIds);

        foreach ($siblings as $sibling) {
            $this->moveTo($sibling, $positions[$sibling->id] + 1);
        }
    }

    /**
     * @param  Collection<int, MainCriterion>|Collection<int, SubCriterion>|Collection<int, GuidingPoint>  $siblings
     */
    public function resequence(Collection $siblings): void
    {
        foreach ($siblings->values() as $index => $sibling) {
            $this->moveTo($sibling, $index + 1);
        }
    }

    /**
     * @param  array<array-key, mixed>  $currentIds
     * @param  list<int>  $orderedIds
     */
    private function assertPermutation(array $currentIds, array $orderedIds): void
    {
        $current = array_values(array_map(static fn (mixed $id): int => ScalarValue::int($id), $currentIds));
        $requested = $orderedIds;
        sort($current);
        sort($requested);

        if ($current !== $requested) {
            throw CriteriaTreeRejected::orderIncomplete();
        }
    }

    private function moveTo(MainCriterion|SubCriterion|GuidingPoint $sibling, int $position): void
    {
        if ($sibling->sort_order !== $position) {
            $sibling->sort_order = $position;
            $sibling->save();
        }
    }
}
