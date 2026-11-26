<?php

declare(strict_types=1);

namespace App\Support\Nda;

/**
 * Shows enough of an IP address to tell sessions apart without displaying the full address: the last IPv4 octet
 * and the last five IPv6 groups are hidden. The full address stays in the record and in the audited export.
 */
final class MaskedIpAddress
{
    private const string HIDDEN = '•';

    public static function of(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? self::ipv4($ip) : self::ipv6($ip);
    }

    private static function ipv4(string $ip): string
    {
        $octets = explode('.', $ip);
        $octets[3] = self::HIDDEN;

        return implode('.', $octets);
    }

    private static function ipv6(string $ip): string
    {
        $expanded = inet_pton($ip);

        if ($expanded === false) {
            return self::HIDDEN;
        }

        $groups = str_split(bin2hex($expanded), 4);

        return implode(':', [...array_slice($groups, 0, 3), self::HIDDEN]);
    }
}
