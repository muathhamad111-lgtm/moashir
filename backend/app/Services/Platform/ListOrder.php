<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Exceptions\Platform\OrderRejected;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class ListOrder
{
    private const string COLUMN = 'sort_order';

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $siblings
     * @param  list<int>  $orderedIds
     */
    public function apply(Collection $siblings, array $orderedIds): void
    {
        $current = array_map(static fn (mixed $id): int => ScalarValue::int($id), $siblings->modelKeys());
        $requested = $orderedIds;
        sort($current);
        sort($requested);

        if ($current !== $requested) {
            throw OrderRejected::incomplete();
        }

        $positions = array_flip($orderedIds);

        foreach ($siblings as $sibling) {
            $this->moveTo($sibling, $positions[ScalarValue::int($sibling->getKey())] + 1);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $siblings
     */
    public function resequence(Collection $siblings): void
    {
        foreach ($siblings->values() as $index => $sibling) {
            $this->moveTo($sibling, $index + 1);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $orderedSiblings
     */
    public function shift(Collection $orderedSiblings, int $id, int $offset): void
    {
        $ids = array_values(array_map(static fn (mixed $key): int => ScalarValue::int($key), $orderedSiblings->modelKeys()));
        $from = array_search($id, $ids, true);
        $to = is_int($from) ? $from + $offset : -1;

        if (! is_int($from) || $to < 0 || $to >= count($ids)) {
            $this->resequence($orderedSiblings);

            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        $this->apply($orderedSiblings, array_values($ids));
    }

    public function nextPosition(mixed $highest): int
    {
        return ScalarValue::int($highest) + 1;
    }

    private function moveTo(Model $sibling, int $position): void
    {
        if (ScalarValue::int($sibling->getAttribute(self::COLUMN)) !== $position) {
            $sibling->forceFill([self::COLUMN => $position])->save();
        }
    }
}
