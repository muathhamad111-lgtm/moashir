<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Enums\Identity\Locale;
use App\Support\StringList;

final class SampleLocales
{
    /**
     * Reads a repeatable --locale console option; none given means both languages.
     *
     * @return list<Locale>|null null when a value is not ar or en
     */
    public static function from(mixed $option): ?array
    {
        $requested = StringList::from($option);
        $locales = [];

        foreach ($requested === [] ? [Locale::Arabic->value, Locale::English->value] : $requested as $value) {
            $locale = Locale::tryFrom($value);

            if (! $locale instanceof Locale) {
                return null;
            }

            $locales[] = $locale;
        }

        return $locales;
    }
}
