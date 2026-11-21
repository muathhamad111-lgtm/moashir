<?php

declare(strict_types=1);

namespace App\Support\Periods;

use App\Enums\Periods\PeriodCadence;
use App\Support\ValueObjects\PeriodWindow;
use Carbon\CarbonImmutable;

final class PeriodCalendar
{
    public static function startingOn(PeriodCadence $cadence, CarbonImmutable $day): PeriodWindow
    {
        return PeriodWindow::containing($cadence, $day)->startingOn($day);
    }

    /**
     * @return list<PeriodWindow>
     */
    public static function sequence(PeriodCadence $cadence, CarbonImmutable $firstDay, int $count): array
    {
        $windows = [];
        $window = self::startingOn($cadence, $firstDay);

        while (count($windows) < $count) {
            $windows[] = $window;
            $window = $window->next();
        }

        return $windows;
    }

    /**
     * @return list<PeriodWindow>
     */
    public static function covering(PeriodCadence $cadence, CarbonImmutable $firstDay, CarbonImmutable $lastDay): array
    {
        $windows = [];
        $window = self::startingOn($cadence, $firstDay);

        while ($window->startsOn->toDateString() <= $lastDay->toDateString()) {
            $windows[] = $window;
            $window = $window->next();
        }

        return $windows;
    }
}
