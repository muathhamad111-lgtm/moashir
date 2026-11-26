<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prints a stored decimal amount with thousands separators, keeping its fraction digits as stored
 * (no float rounding): "1500.00" becomes "1,500.00".
 */
final class GroupedAmount
{
    public static function format(string $amount): string
    {
        $parts = explode('.', $amount, 2);
        $grouped = preg_replace('/\B(?=(\d{3})+$)/', ',', $parts[0]) ?? $parts[0];

        return isset($parts[1]) ? $grouped.'.'.$parts[1] : $grouped;
    }
}
