<?php

declare(strict_types=1);

namespace App\Support\Integrations;

final class IpAddressPolicy
{
    private const array BLOCKED_IPV4 = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    private const array BLOCKED_IPV6 = [
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '100::/64',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    public static function isPublic(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $packed = (string) inet_pton($address);

        $ranges = strlen($packed) === 4 ? self::BLOCKED_IPV4 : self::BLOCKED_IPV6;

        return array_all($ranges, fn (string $range): bool => ! self::contains($range, $packed));
    }

    public static function isLiteral(string $host): bool
    {
        return filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
    }

    private static function contains(string $range, string $packedAddress): bool
    {
        [$network, $prefix] = explode('/', $range);
        $packedNetwork = (string) inet_pton($network);

        if (strlen($packedNetwork) !== strlen($packedAddress)) {
            return false;
        }

        $bits = (int) $prefix;
        $fullBytes = intdiv($bits, 8);

        if (strncmp($packedNetwork, $packedAddress, $fullBytes) !== 0) {
            return false;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packedNetwork[$fullBytes]) & $mask) === (ord($packedAddress[$fullBytes]) & $mask);
    }
}
