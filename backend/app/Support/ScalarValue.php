<?php

declare(strict_types=1);

namespace App\Support;

final class ScalarValue
{
    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : $default;
    }
}
