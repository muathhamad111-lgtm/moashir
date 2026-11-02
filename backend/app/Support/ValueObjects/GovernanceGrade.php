<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class GovernanceGrade
{
    private const int MAXIMUM_HUNDREDTHS = 10000;

    private function __construct(public int $hundredths) {}

    public static function of(string|int|float $value): self
    {
        $hundredths = Weight::of($value)->hundredths;

        if ($hundredths < 0 || $hundredths > self::MAXIMUM_HUNDREDTHS) {
            throw new InvalidArgumentException('A governance grade is a percentage between 0 and 100.');
        }

        return new self($hundredths);
    }

    public function toDecimal(): string
    {
        return Weight::fromHundredths($this->hundredths)->toDecimal();
    }
}
