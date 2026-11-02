<?php

declare(strict_types=1);

namespace App\Support;

final class CsvCell
{
    private const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function neutralize(string $cell): string
    {
        return $cell !== '' && in_array($cell[0], self::FORMULA_TRIGGERS, true) ? "'".$cell : $cell;
    }
}
