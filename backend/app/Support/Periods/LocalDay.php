<?php

declare(strict_types=1);

namespace App\Support\Periods;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final class LocalDay
{
    public static function timezone(): string
    {
        return config()->string('app.display_timezone');
    }

    public static function today(): CarbonImmutable
    {
        return self::of(Date::now());
    }

    public static function of(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::timezone())->startOfDay();
    }

    public static function parse(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->startOfDay();
    }

    public static function endOfDayInstant(CarbonInterface $day): CarbonImmutable
    {
        return self::parse($day->toDateString())->addDay()->utc();
    }
}
