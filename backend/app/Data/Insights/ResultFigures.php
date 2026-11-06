<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Support\FixedPoint;
use App\Support\ValueObjects\Percent;

final readonly class ResultFigures
{
    private const int DECIMALS = 2;

    public function __construct(public string $weight, public string $earned, public Percent $percent) {}

    public function weightHundredths(): int
    {
        return FixedPoint::parse($this->weight, self::DECIMALS) ?? 0;
    }

    public function lostHundredths(): int
    {
        return max(0, $this->weightHundredths() - (FixedPoint::parse($this->earned, self::DECIMALS) ?? 0));
    }

    public function lostPoints(): string
    {
        return FixedPoint::format($this->lostHundredths(), self::DECIMALS);
    }
}
