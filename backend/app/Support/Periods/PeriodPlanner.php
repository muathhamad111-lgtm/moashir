<?php

declare(strict_types=1);

namespace App\Support\Periods;

use App\Data\Periods\PeriodicSettings;
use App\Data\Periods\PeriodPlan;
use Carbon\CarbonImmutable;

final class PeriodPlanner
{
    public const int UPCOMING_PERIODS = 4;

    public static function plan(PeriodicSettings $settings, CarbonImmutable $today, ?CarbonImmutable $lastStartedEndsOn): PeriodPlan
    {
        if (! $settings->rolloutFrom instanceof CarbonImmutable) {
            return PeriodPlan::empty();
        }

        $cursor = $lastStartedEndsOn instanceof CarbonImmutable ? $lastStartedEndsOn->addDay() : $settings->rolloutFrom;
        $started = $cursor->toDateString() <= $today->toDateString() ? PeriodCalendar::covering($settings->cadence, $cursor, $today) : [];
        $nextStart = $started === [] ? $cursor : $started[array_key_last($started)]->endsOn->addDay();

        return new PeriodPlan($started, PeriodCalendar::sequence($settings->cadence, $nextStart, self::UPCOMING_PERIODS));
    }
}
