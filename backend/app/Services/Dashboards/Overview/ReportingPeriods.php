<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * Turns a period type and the day it covers (or a custom pair of days) into a window in the
 * display time zone. A month, quarter or year runs to its end, or to the end of today while it runs.
 */
final class ReportingPeriods
{
    private const string MONTH_FORMAT = '!Y-m';

    public function preset(OverviewPeriod $preset, ?CarbonImmutable $within = null): ReportingPeriod
    {
        $months = $preset->months() ?? throw new InvalidArgumentException('A custom period needs its own days.');
        $day = $within ?? $this->today();

        $start = match ($preset) {
            OverviewPeriod::Quarter => $day->startOfQuarter(),
            OverviewPeriod::Year => $day->startOfYear(),
            default => $day->startOfMonth(),
        };

        return new ReportingPeriod($preset, $start, $start->addMonthsNoOverflow($months)->min($this->today()->addDay()));
    }

    public function custom(string $fromDay, string $toDay): ReportingPeriod
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $fromDay, $this->zone())?->startOfDay();
        $lastDay = CarbonImmutable::createFromFormat('Y-m-d', $toDay, $this->zone())?->startOfDay();

        return new ReportingPeriod(OverviewPeriod::Custom, $start ?? $this->today(), ($lastDay ?? $this->today())->addDay());
    }

    /**
     * The first day of the given calendar month, or null when the year and month do not form one.
     */
    public function monthStart(int $year, int $month): ?CarbonImmutable
    {
        if ($month < 1 || $month > 12) {
            return null;
        }

        return CarbonImmutable::create($year, $month, 1, 0, 0, 0, $this->zone());
    }

    /**
     * The first day the boards cover (config moashir.dashboard.first_month).
     */
    public function firstDay(): CarbonImmutable
    {
        $first = CarbonImmutable::createFromFormat(self::MONTH_FORMAT, config()->string('moashir.dashboard.first_month'), $this->zone());

        return ($first ?? $this->today())->startOfMonth();
    }

    public function today(): CarbonImmutable
    {
        return Date::now($this->zone())->toImmutable()->startOfDay();
    }

    private function zone(): string
    {
        return config()->string('app.display_timezone');
    }
}
