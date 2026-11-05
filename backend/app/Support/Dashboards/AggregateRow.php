<?php

declare(strict_types=1);

namespace App\Support\Dashboards;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AggregateRow
{
    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     * @param  literal-string  $select
     * @param  list<mixed>  $bindings
     * @return array<string, mixed>
     */
    public static function fetch(EloquentBuilder|QueryBuilder $query, string $select, array $bindings = []): array
    {
        $base = $query instanceof EloquentBuilder ? $query->toBase() : $query;
        $row = (clone $base)->reorder()->selectRaw($select, $bindings)->first();
        $values = [];

        foreach (is_object($row) ? get_object_vars($row) : [] as $column => $value) {
            $values[(string) $column] = $value;
        }

        return $values;
    }

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     * @param  array<literal-string, array{0: literal-string, 1: list<mixed>}>  $conditions
     * @return array<string, int>
     */
    public static function counts(EloquentBuilder|QueryBuilder $query, array $conditions): array
    {
        $select = 'true as "_"';
        $bindings = [];

        foreach ($conditions as $key => [$condition, $values]) {
            $select .= ', count(*) filter (where '.$condition.') as "'.$key.'"';
            array_push($bindings, ...$values);
        }

        $row = self::fetch($query, $select, $bindings);
        unset($row['_']);

        return array_map(static fn (mixed $count): int => is_numeric($count) ? (int) $count : 0, $row);
    }

    /**
     * @param  list<mixed>  $values
     * @return literal-string
     */
    public static function placeholders(array $values): string
    {
        $marks = '?';

        for ($index = 1; $index < count($values); $index++) {
            $marks .= ', ?';
        }

        return $marks;
    }
}
