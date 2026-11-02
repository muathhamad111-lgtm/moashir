<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\FixedPoint;
use InvalidArgumentException;

final readonly class EarnedScore
{
    public const int DECIMALS = 8;

    private const int DISPLAY_DIVISOR = 1_000_000;

    private function __construct(public int $hundredMillionths)
    {
        if ($hundredMillionths < 0) {
            throw new InvalidArgumentException('An earned score cannot be negative.');
        }
    }

    public static function of(string $value): self
    {
        $parsed = FixedPoint::parse($value, self::DECIMALS);

        return $parsed === null
            ? throw new InvalidArgumentException('An earned score must be a decimal number with at most eight decimal places.')
            : new self($parsed);
    }

    public static function fromHundredMillionths(int $hundredMillionths): self
    {
        return new self($hundredMillionths);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofPercentOfWeight(Percent $percent, Weight $weight): self
    {
        return new self($percent->tenThousandths * max(0, $weight->hundredths));
    }

    /**
     * @param  iterable<self>  $scores
     */
    public static function sum(iterable $scores): self
    {
        $total = 0;

        foreach ($scores as $score) {
            $total += $score->hundredMillionths;
        }

        return new self($total);
    }

    public function percentOf(Weight $weight): Percent
    {
        if (! $weight->isPositive()) {
            return Percent::zero();
        }

        return Percent::fromTenThousandths(min(Percent::HUNDRED, FixedPoint::divideRoundingHalfUp($this->hundredMillionths, $weight->hundredths)));
    }

    public function toDecimal(): string
    {
        return FixedPoint::format($this->hundredMillionths, self::DECIMALS);
    }

    public function toDisplay(): string
    {
        return FixedPoint::format(FixedPoint::divideRoundingHalfUp($this->hundredMillionths, self::DISPLAY_DIVISOR), 2);
    }
}
