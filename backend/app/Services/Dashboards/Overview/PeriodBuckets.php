<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\ReportingPeriod;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Splits a reporting period into its trend buckets (a day, a week or a month each)
 * and sums a query per bucket. Buckets with no rows carry the given zero.
 */
final readonly class PeriodBuckets
{
    private const string BUCKET_FORMAT = 'Y-m-d';

    /**
     * @return list<string> ISO dates of each bucket's first day
     */
    public function keys(ReportingPeriod $period): array
    {
        $bucket = $period->bucket();
        $keys = [];
        for ($cursor = $bucket->startOf($period->start); $cursor->lessThan($period->end); $cursor = $bucket->next($cursor)) {
            $keys[] = $cursor->format(self::BUCKET_FORMAT);
        }

        return $keys;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @return list<int>
     */
    public function counts(Builder $query, string $column, ReportingPeriod $period): array
    {
        $values = $this->grouped($query, $column, $period, 'count(*)');

        return array_map(static fn (string $bucket): int => ScalarValue::int($values[$bucket] ?? null), $this->keys($period));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @param  literal-string  $aggregate
     * @return list<string>
     */
    public function sums(Builder $query, string $column, ReportingPeriod $period, string $aggregate, string $zero): array
    {
        $values = $this->grouped($query, $column, $period, $aggregate);

        return array_map(static fn (string $bucket): string => ScalarValue::string($values[$bucket] ?? null, $zero), $this->keys($period));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @param  literal-string  $aggregate
     * @return array<string, mixed>
     */
    private function grouped(Builder $query, string $column, ReportingPeriod $period, string $aggregate): array
    {
        $local = 'date_trunc(?, '.$column.' at time zone ?)';
        $bindings = [$period->bucket()->value, $period->start->getTimezone()->getName()];
        $rows = (clone $query)->toBase()->reorder()
            ->where($column, '>=', $period->start->toIso8601String())
            ->where($column, '<', $period->end->toIso8601String())
            ->selectRaw("to_char({$local}, 'YYYY-MM-DD') as bucket, {$aggregate} as amount", $bindings)
            // Grouped by the first selected column: Postgres would not match a repeated parameterised expression.
            ->groupByRaw('1')
            ->get();

        $values = [];
        foreach ($rows as $row) {
            $columns = get_object_vars($row);
            $values[ScalarValue::string($columns['bucket'] ?? null)] = $columns['amount'] ?? null;
        }

        return $values;
    }
}
