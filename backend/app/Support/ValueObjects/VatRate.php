<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class VatRate
{
    private const string PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';

    private const int BASIS_POINTS_PER_WHOLE = 10_000;

    public string $percent;

    public function __construct(string $percent)
    {
        $value = trim($percent);

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('The VAT rate is a percentage with at most two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $normalized = sprintf('%d.%s', (int) $whole, str_pad($fraction, 2, '0'));

        if ((int) $whole > 100 || ((int) $whole === 100 && $fraction !== '' && (int) $fraction > 0)) {
            throw new InvalidArgumentException('The VAT rate cannot exceed 100 percent.');
        }

        $this->percent = $normalized;
    }

    public function basisPoints(): int
    {
        [$whole, $fraction] = explode('.', $this->percent);

        return ((int) $whole * 100) + (int) $fraction;
    }

    public function of(Money $price): Money
    {
        $product = $price->inMinorUnits() * $this->basisPoints();

        return Money::fromMinorUnits(intdiv($product + intdiv(self::BASIS_POINTS_PER_WHOLE, 2), self::BASIS_POINTS_PER_WHOLE), $price->currency);
    }
}
