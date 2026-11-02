<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use IntlDateFormatter;

final class HijriDate
{
    private const string PATTERN = 'd MMMM y';

    public static function format(DateTimeInterface $date, string $locale, string $timezone): string
    {
        $formatter = new IntlDateFormatter(
            ($locale === 'en' ? 'en' : 'ar_SA').'@calendar=islamic-umalqura;numbers=latn',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $timezone,
            IntlDateFormatter::TRADITIONAL,
            self::PATTERN,
        );

        return (string) $formatter->format($date);
    }
}
