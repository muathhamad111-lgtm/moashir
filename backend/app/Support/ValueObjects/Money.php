<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public const string SAUDI_RIYAL = 'SAR';

    private const string AMOUNT_PATTERN = '/^\d{1,10}\.\d{2}$/';

    private const int MINOR_UNITS_PER_UNIT = 100;

    public function __construct(public string $amount, public string $currency = self::SAUDI_RIYAL)
    {
        if (preg_match(self::AMOUNT_PATTERN, $amount) !== 1) {
            throw new InvalidArgumentException('Money amounts are decimal strings with two places.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('The currency must be an ISO-4217 code.');
        }
    }

    public static function fromMinorUnits(int $units, string $currency = self::SAUDI_RIYAL): self
    {
        if ($units < 0) {
            throw new InvalidArgumentException('Money amounts cannot be negative.');
        }

        return new self(sprintf('%d.%02d', intdiv($units, self::MINOR_UNITS_PER_UNIT), $units % self::MINOR_UNITS_PER_UNIT), $currency);
    }

    public static function zero(string $currency = self::SAUDI_RIYAL): self
    {
        return self::fromMinorUnits(0, $currency);
    }

    public function inMinorUnits(): int
    {
        [$units, $fraction] = explode('.', $this->amount);

        return ((int) $units * self::MINOR_UNITS_PER_UNIT) + (int) $fraction;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinorUnits($this->inMinorUnits() + $other->inMinorUnits(), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->inMinorUnits() === $other->inMinorUnits();
    }

    public function isPositive(): bool
    {
        return $this->inMinorUnits() > 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Money amounts of different currencies cannot be combined.');
        }
    }
}
