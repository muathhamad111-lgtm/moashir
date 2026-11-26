<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum MaturityBand: string
{
    case Emerging = 'emerging';
    case Developing = 'developing';
    case Mature = 'mature';
    case Leading = 'leading';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
