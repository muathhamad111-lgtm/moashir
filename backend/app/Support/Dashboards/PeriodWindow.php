<?php

declare(strict_types=1);

namespace App\Support\Dashboards;

use App\Data\Dashboards\ReportingPeriod;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PeriodWindow
{
    private const string DAY_FORMAT = 'Y-m-d';

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     */
    public static function moments(EloquentBuilder|QueryBuilder $query, string $column, ReportingPeriod $period): void
    {
        $query->where($column, '>=', $period->start->toIso8601String())->where($column, '<', $period->end->toIso8601String());
    }

    /**
     * @param  EloquentBuilder<covariant Model>|QueryBuilder  $query
     */
    public static function days(EloquentBuilder|QueryBuilder $query, string $column, ReportingPeriod $period): void
    {
        $query->where($column, '>=', $period->start->format(self::DAY_FORMAT))->where($column, '<=', $period->lastDay()->format(self::DAY_FORMAT));
    }

    /**
     * @return list<string> the period's first and end moments, for a raw `column >= ? and column < ?` window
     */
    public static function bindings(ReportingPeriod $period): array
    {
        return [$period->start->toIso8601String(), $period->end->toIso8601String()];
    }
}
