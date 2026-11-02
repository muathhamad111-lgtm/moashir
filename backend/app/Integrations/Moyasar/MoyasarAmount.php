<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Support\ValueObjects\Money;
use InvalidArgumentException;

final class MoyasarAmount
{
    public static function toHalalas(Money $money): int
    {
        return $money->inMinorUnits();
    }

    public static function fromHalalas(mixed $amount, string $currency): Money
    {
        if (! is_int($amount) || $amount < 0) {
            throw new InvalidArgumentException('Moyasar amounts are non-negative integers in halalas.');
        }

        return Money::fromMinorUnits($amount, $currency);
    }
}
