<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class StatusCounts
{
    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<BackedEnum>  $statuses
     * @return array<string, int>
     */
    public static function of(Builder $query, array $statuses): array
    {
        $totals = (clone $query)->toBase()->reorder()->select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [];

        foreach ($statuses as $status) {
            $counts[(string) $status->value] = ScalarValue::int($totals->get((string) $status->value));
        }

        return $counts;
    }
}
