<?php

declare(strict_types=1);

namespace App\Support\Integrations;

final class SmsTemplateMatcher
{
    /**
     * @param  list<string>  $approvedBodies
     */
    public static function matchesAny(string $body, array $approvedBodies): bool
    {
        $candidate = self::normalized($body);

        return array_any($approvedBodies, static fn (string $approved): bool => self::normalized($approved) === $candidate);
    }

    public static function normalized(string $text): string
    {
        $withPlaceholders = (string) preg_replace('/\{\{[^{}\s]{1,40}\}\}|\{[^{}\s]{1,40}\}|\[[^\[\]\s]{1,40}\]|%s|\bx{4,}\b/ui', '{}', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $withPlaceholders));
    }
}
