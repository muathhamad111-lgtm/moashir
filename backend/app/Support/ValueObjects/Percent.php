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
}
