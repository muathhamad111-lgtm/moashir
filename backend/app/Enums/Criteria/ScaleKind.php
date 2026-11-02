<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

use App\Support\FixedPoint;

enum ScaleKind: string
{
    case OutOfTen = 'of_10';
    case Relative = 'relative';

    public function maximumHundredths(): int
    {
        return match ($this) {
            self::OutOfTen => 1_000,
            self::Relative => 10_000,
        };
    }

    public function maximum(): string
    {
        return FixedPoint::format($this->maximumHundredths(), 2);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
