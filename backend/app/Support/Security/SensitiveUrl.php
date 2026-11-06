<?php

declare(strict_types=1);

namespace App\Support\Security;

final class SensitiveUrl
{
    public const string REDACTED = '[redacted]';

    private const array TOKEN_PATHS = [
        '#(/auth/invitations/)[^/?\#\s"\']+#i',
        '#(/auth/reset-password/)[^/?\#\s"\']+#i',
    ];

    private const string LONG_TOKEN_SEGMENT = '#(?<=/)[A-Za-z0-9]{32,}(?=[/?\#\s"\']|$)#';

    public static function redact(string $text): string
    {
        $withoutKnownTokens = (string) preg_replace(self::TOKEN_PATHS, '$1'.self::REDACTED, $text);

        return (string) preg_replace(self::LONG_TOKEN_SEGMENT, self::REDACTED, $withoutKnownTokens);
    }
}
