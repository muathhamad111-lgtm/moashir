<?php

declare(strict_types=1);

namespace App\Support\Dashboards;

final class Ratio
{
    private const int HUNDREDTHS_OF_PERCENT = 10000;

    public static function percent(int $part, int $whole): ?string
    {
        if ($whole <= 0) {
            return null;
        }

        $hundredths = intdiv(2 * $part * self::HUNDREDTHS_OF_PERCENT + $whole, 2 * $whole);

        return sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);
    }
}
