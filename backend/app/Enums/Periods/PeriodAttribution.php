<?php

declare(strict_types=1);

namespace App\Enums\Periods;

enum PeriodAttribution: string
{
    case Assigned = 'assigned';
    case PrePeriodic = 'pre_periodic';
    case Pending = 'pending';
    case None = 'none';

    public function labelKey(): ?string
    {
        return match ($this) {
            self::PrePeriodic => 'assessments.periods.attribution.pre_periodic',
            self::Pending => 'assessments.periods.attribution.pending',
            self::Assigned, self::None => null,
        };
    }
}
