<?php

declare(strict_types=1);

namespace App\Support\Notifications\Templates;

final class TemplatePlaceholders
{
    private const string PATTERN = '/:([a-z][a-z0-9_]*)/i';

    /**
     * @return list<string>
     */
    public static function in(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique(array_map(strtolower(...), $matches[1])));
    }

    /**
     * @param  list<string>  $required
     * @return list<string>
     */
    public static function missingFrom(string $text, array $required): array
    {
        return array_values(array_diff($required, self::in($text)));
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function unknownIn(string $text, array $allowed): array
    {
        return array_values(array_diff(self::in($text), $allowed));
    }
}
