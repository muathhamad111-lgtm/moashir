<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\FixedPoint;
use InvalidArgumentException;

final readonly class Percent
{
    public const int DECIMALS = 4;

    public const int HUNDRED = 1_000_000;

    private const int DISPLAY_DIVISOR = 100;

    private const int DISPLAY_DECIMALS = 2;

    private function __construct(public int $tenThousandths)
    {
        if ($tenThousandths < 0 || $tenThousandths > self::HUNDRED) {
            throw new InvalidArgumentException('A percentage must lie between 0 and 100.');
        }
    }

    public static function of(string $value): self
    {
        $parsed = FixedPoint::parse($value, self::DECIMALS);

        return $parsed === null
            ? throw new InvalidArgumentException('A percentage must be a decimal number with at most four decimal places.')
            : new self($parsed);
    }

    public static function ofDisplayed(string $value): self
    {
        if (FixedPoint::parse($value, self::DISPLAY_DECIMALS) === null) {
            throw new InvalidArgumentException('A displayed percentage must have at most two decimal places.');
        }

        return self::of($value);
    }

    public static function fromTenThousandths(int $tenThousandths): self
    {
        return new self($tenThousandths);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function equals(self $other): bool
    {
        return $this->tenThousandths === $other->tenThousandths;
    }

    public function toDecimal(): string
    {
        return FixedPoint::format($this->tenThousandths, self::DECIMALS);
    }

    public function toDisplay(): string
    {
        return FixedPoint::format($this->displayedHundredths(), self::DISPLAY_DECIMALS);
    }

    public function displayedHundredths(): int
    {
        return FixedPoint::divideRoundingHalfUp($this->tenThousandths, self::DISPLAY_DIVISOR);
    }

    /**
     * The lowest stored percent whose displayed value (2 decimals, half-up) reaches this percent.
     */
    public function lowestDisplayedAtLeast(): self
    {
        $ceiling = intdiv($this->tenThousandths + self::DISPLAY_DIVISOR - 1, self::DISPLAY_DIVISOR) * self::DISPLAY_DIVISOR;

        return new self(max(0, $ceiling - intdiv(self::DISPLAY_DIVISOR, 2)));
    }

    /**
     * Compares what people see: 59.995 is displayed as 60.00 and therefore reaches a 60.00 threshold.
     */
    public function isDisplayedBelow(self $threshold): bool
    {
        return $this->tenThousandths < $threshold->lowestDisplayedAtLeast()->tenThousandths;
    }
}
