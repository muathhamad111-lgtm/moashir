<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * Turns a preset (or a custom pair of days) into a window in the display time zone.
 * Presets run from the start of the current month, quarter or year to the end of today.
 */
final class ReportingPeriods
{
    public function preset(OverviewPeriod $preset): ReportingPeriod
    {
        $today = $this->today();

        $start = match ($preset) {
            OverviewPeriod::Month => $today->startOfMonth(),
            OverviewPeriod::Quarter => $today->startOfQuarter(),
            OverviewPeriod::Year => $today->startOfYear(),
            OverviewPeriod::Custom => throw new InvalidArgumentException('A custom period needs its own days.'),
        };

        return new ReportingPeriod($preset, $start, $today->addDay());
    }

    public function custom(string $fromDay, string $toDay): ReportingPeriod
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $fromDay, $this->zone())?->startOfDay();
        $lastDay = CarbonImmutable::createFromFormat('Y-m-d', $toDay, $this->zone())?->startOfDay();

        return new ReportingPeriod(OverviewPeriod::Custom, $start ?? $this->today(), ($lastDay ?? $this->today())->addDay());
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
