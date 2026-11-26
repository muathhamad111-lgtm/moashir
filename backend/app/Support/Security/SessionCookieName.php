<?php

declare(strict_types=1);

namespace App\Support\Security;

final class SessionCookieName
{
    public const string HOST_PREFIX = '__Host-';

    private const string ROOT_PATH = '/';

    public static function resolve(string $baseName, bool $isSecure, ?string $domain, string $path): string
    {
        $isHostOnly = $isSecure && ($domain === null || $domain === '') && $path === self::ROOT_PATH;
        $name = str_starts_with($baseName, self::HOST_PREFIX) ? substr($baseName, strlen(self::HOST_PREFIX)) : $baseName;

        return $isHostOnly ? self::HOST_PREFIX.$name : $name;
    }
}
