<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\FixedPoint;

final readonly class PointsDelta
{
    private const int DECIMALS = 2;

    private function __construct(public int $hundredths) {}

    public static function between(Percent $current, Percent $previous): self
    {
        return new self($current->displayedHundredths() - $previous->displayedHundredths());
    }

    public function toDisplay(): string
    {
        return ($this->hundredths < 0 ? '-' : '').FixedPoint::format(abs($this->hundredths), self::DECIMALS);
    }
}
