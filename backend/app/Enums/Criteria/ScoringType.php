<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

/**
 * How one scored unit (a directly evaluated sub-criterion or an indicator) is scored: a percentage, a grade out of
 * ten, or achieved / not achieved (operator decisions D-1 to D-3, 2026-11-19).
 */
enum ScoringType: string
{
    case Relative = 'relative';
    case Score = 'score';
    case Achievement = 'achievement';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
