<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\FixedPoint;
use InvalidArgumentException;

final readonly class GovernanceGrade
{
    public const int DECIMALS = 2;

    private const int MAXIMUM_HUNDREDTHS = 10_000;

    private function __construct(public int $hundredths) {}

    public static function of(string|int|float $value): self
    {
        $hundredths = FixedPoint::parse((string) $value, self::DECIMALS);

        if ($hundredths === null || $hundredths > self::MAXIMUM_HUNDREDTHS) {
            throw new InvalidArgumentException('A governance grade is a percentage between 0 and 100 with at most two decimals.');
        }

        return new self($hundredths);
    }

    public function toDecimal(): string
    {
        return FixedPoint::format($this->hundredths, self::DECIMALS);
    }
}
