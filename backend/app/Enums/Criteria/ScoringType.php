<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

use App\Support\FixedPoint;

/**
 * How one scored unit (a directly evaluated sub-criterion or an indicator) is scored: a percentage, a grade out of
 * ten, or achieved / not achieved (operator decisions D-1 to D-3, 2026-11-19).
 */
enum ScoringType: string
{
    case Relative = 'relative';
    case Score = 'score';
    case Achievement = 'achievement';

    private const int ACHIEVED_HUNDREDTHS = 100;

    public function maximumHundredths(): int
    {
        return match ($this) {
            self::Relative => 10_000,
            self::Score => 1_000,
            self::Achievement => self::ACHIEVED_HUNDREDTHS,
        };
    }

    public function maximum(): string
    {
        return FixedPoint::format($this->maximumHundredths(), 2);
    }

    /**
     * Whether a raw value in hundredths is allowed: within the range, and for achieved / not achieved only 0 or 1.
     */
    public function accepts(int $hundredths): bool
    {
        if ($hundredths < 0 || $hundredths > $this->maximumHundredths()) {
            return false;
        }

        return $this !== self::Achievement || $hundredths % self::ACHIEVED_HUNDREDTHS === 0;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
