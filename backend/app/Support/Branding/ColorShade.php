<?php

declare(strict_types=1);

namespace App\Support\Branding;

use InvalidArgumentException;

final class ColorShade
{
    public static function darken(string $hex, float $amount): string
    {
        if (! ContrastRatio::isHex($hex)) {
            throw new InvalidArgumentException('A colour must be a six-digit hex value.');
        }

        $keep = 1 - max(0.0, min(1.0, $amount));
        $channels = array_map(
            static fn (string $channel): string => sprintf('%02x', (int) round(hexdec($channel) * $keep)),
            str_split(substr($hex, 1), 2),
        );

        return '#'.implode('', $channels);
    }

    /**
     * Mixes two colours channel by channel in gamma-encoded sRGB, as CSS `color-mix(in srgb, …)`
     * does: $share of $first and the rest of $second.
     */
    public static function mix(string $first, string $second, float $share): string
    {
        if (! ContrastRatio::isHex($first) || ! ContrastRatio::isHex($second)) {
            throw new InvalidArgumentException('A colour must be a six-digit hex value.');
        }

        $weight = max(0.0, min(1.0, $share));
        $channels = array_map(
            static fn (string $a, string $b): string => sprintf('%02x', (int) round(hexdec($a) * $weight + hexdec($b) * (1 - $weight))),
            str_split(substr($first, 1), 2),
            str_split(substr($second, 1), 2),
        );

        return '#'.implode('', $channels);
    }
}
