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

    /**
     * Blends two colours in OKLab: $share of the way from $from to $to. Unlike an sRGB mix, the
     * middle of the blend keeps the lightness and colour of its ends instead of going grey.
     * The SPA mirrors this arithmetic step for step (utils/brandColorMath.js `blend`).
     */
    public static function blend(string $from, string $to, float $share): string
    {
        if (! ContrastRatio::isHex($from) || ! ContrastRatio::isHex($to)) {
            throw new InvalidArgumentException('A colour must be a six-digit hex value.');
        }

        $weight = max(0.0, min(1.0, $share));

        if ($weight === 0.0 || $weight === 1.0) {
            return mb_strtolower($weight === 0.0 ? $from : $to);
        }

        $start = self::toOklab($from);
        $end = self::toOklab($to);

        return self::fromOklab(array_map(static fn (float $a, float $b): float => $a + ($b - $a) * $weight, $start, $end));
    }

    /**
     * @return list<float>
     */
    private static function toOklab(string $hex): array
    {
        [$red, $green, $blue] = array_map(
            static fn (string $channel): float => self::toLinear(hexdec($channel) / 255),
            str_split(substr($hex, 1), 2),
        );
        $long = (0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue) ** (1 / 3);
        $medium = (0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue) ** (1 / 3);
        $short = (0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue) ** (1 / 3);

        return [
            0.2104542553 * $long + 0.793617785 * $medium - 0.0040720468 * $short,
            1.9779984951 * $long - 2.428592205 * $medium + 0.4505937099 * $short,
            0.0259040371 * $long + 0.7827717662 * $medium - 0.808675766 * $short,
        ];
    }

    /**
     * @param  list<float>  $lab
     */
    private static function fromOklab(array $lab): string
    {
        [$lightness, $greenRed, $blueYellow] = $lab;
        $long = ($lightness + 0.3963377774 * $greenRed + 0.2158037573 * $blueYellow) ** 3;
        $medium = ($lightness - 0.1055613458 * $greenRed - 0.0638541728 * $blueYellow) ** 3;
        $short = ($lightness - 0.0894841775 * $greenRed - 1.291485548 * $blueYellow) ** 3;
        $linear = [
            4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short,
            -1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short,
            -0.0041960863 * $long - 0.7034186147 * $medium + 1.707614701 * $short,
        ];

        return '#'.implode('', array_map(
            static fn (float $channel): string => sprintf('%02x', (int) round(max(0.0, min(1.0, self::toGamma($channel))) * 255)),
            $linear,
        ));
    }

    private static function toLinear(float|int $channel): float
    {
        $channel = (float) $channel;

        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }

    private static function toGamma(float $channel): float
    {
        return $channel <= 0.0031308 ? 12.92 * $channel : 1.055 * $channel ** (1 / 2.4) - 0.055;
    }
}
