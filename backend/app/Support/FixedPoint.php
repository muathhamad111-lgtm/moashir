<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class FixedPoint
{
    public static function divideRoundingHalfUp(int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator < 0) {
            throw new InvalidArgumentException('Fixed-point division needs a non-negative numerator and a positive denominator.');
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    public static function multiplyDivideRoundingHalfUp(int $value, int $multiplier, int $divisor): int
    {
        if ($divisor <= 0 || $value < 0 || $multiplier < 0) {
            throw new InvalidArgumentException('Fixed-point scaling needs non-negative factors and a positive divisor.');
        }

        $quotient = intdiv($value, $divisor);
        $remainder = $value % $divisor;

        return $quotient * $multiplier + self::divideRoundingHalfUp($remainder * $multiplier, $divisor);
    }

    public static function format(int $value, int $decimals): string
    {
        $scale = 10 ** $decimals;

        return $decimals === 0
            ? (string) $value
            : sprintf('%d.%0'.$decimals.'d', intdiv($value, $scale), $value % $scale);
    }

    public static function parse(string $text, int $decimals): ?int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d+))?$/', trim($text), $parts) !== 1) {
            return null;
        }

        $fraction = $parts[2] ?? '';

        if (strlen($fraction) > $decimals && trim(substr($fraction, $decimals), '0') !== '') {
            return null;
        }

        return (int) $parts[1] * 10 ** $decimals + (int) str_pad(substr($fraction, 0, $decimals), $decimals, '0');
    }
}
