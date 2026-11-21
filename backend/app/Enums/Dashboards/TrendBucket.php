<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Weeks start on Monday, as Postgres `date_trunc('week', …)` does.
 */
enum TrendBucket: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    private const int DAILY_UP_TO_DAYS = 31;

    private const int WEEKLY_UP_TO_DAYS = 92;

    public static function forDays(int $days): self
    {
        return match (true) {
            $days <= self::DAILY_UP_TO_DAYS => self::Day,
            $days <= self::WEEKLY_UP_TO_DAYS => self::Week,
            default => self::Month,
        };
    }

    public function startOf(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Day => $moment->startOfDay(),
            self::Week => $moment->startOfWeek(CarbonInterface::MONDAY),
            self::Month => $moment->startOfMonth(),
        };
    }

    public function next(CarbonImmutable $bucketStart): CarbonImmutable
    {
        return match ($this) {
            self::Day => $bucketStart->addDay(),
            self::Week => $bucketStart->addWeek(),
            self::Month => $bucketStart->addMonth(),
        };
    }
}
