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
}
