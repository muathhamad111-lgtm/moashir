<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\FixedPoint;
use InvalidArgumentException;

final readonly class GovernanceGrade
{
    public const int DECIMALS = 3;

    private const int MAXIMUM_THOUSANDTHS = 100_000;

    private function __construct(public int $thousandths) {}

    public static function of(string|int|float $value): self
    {
        $thousandths = FixedPoint::parse((string) $value, self::DECIMALS);

        if ($thousandths === null || $thousandths > self::MAXIMUM_THOUSANDTHS) {
            throw new InvalidArgumentException('A governance grade is a percentage between 0 and 100 with at most three decimals.');
        }

        return new self($thousandths);
    }

    public function toDecimal(): string
    {
        return FixedPoint::format($this->thousandths, self::DECIMALS);
    }
}
