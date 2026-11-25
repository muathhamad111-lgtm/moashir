<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

enum OverviewPeriod: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';
    case Custom = 'custom';

    /**
     * The calendar months one period of this type spans; a custom range has none.
     */
    public function months(): ?int
    {
        return match ($this) {
            self::Month => 1,
            self::Quarter => 3,
            self::Year => 12,
            self::Custom => null,
        };
    }
}
