<?php

declare(strict_types=1);

namespace App\Support\Branding;

use InvalidArgumentException;

final class ContrastRatio
{
    private const string HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public static function between(string $foreground, string $background): float
    {
        $first = self::luminance($foreground);
        $second = self::luminance($background);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    public static function format(float $ratio): string
    {
        return sprintf('%.2f', $ratio);
    }

    public static function meets(float $ratio, string $minimum): bool
    {
        return is_numeric($minimum) && $ratio >= (float) $minimum;
    }

    public static function isHex(string $color): bool
    {
        return preg_match(self::HEX_PATTERN, $color) === 1;
    }

    private static function luminance(string $hex): float
    {
        if (! self::isHex($hex)) {
            throw new InvalidArgumentException('A colour must be a six-digit hex value.');
        }

        [$red, $green, $blue] = array_map(
            static fn (string $channel): float => self::linear(hexdec($channel) / 255),
            str_split(substr($hex, 1), 2),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private static function linear(float|int $channel): float
    {
        $channel = (float) $channel;

        return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
