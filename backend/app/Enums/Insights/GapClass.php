<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum GapClass: string
{
    case Strength = 'strength';
    case Opportunity = 'opportunity';
    case CriticalGap = 'critical_gap';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
