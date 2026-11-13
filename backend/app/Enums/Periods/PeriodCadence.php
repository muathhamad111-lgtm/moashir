<?php

declare(strict_types=1);

namespace App\Enums\Periods;

enum PeriodCadence: string
{
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    private const int MONTHS_PER_YEAR = 12;

    public function monthsPerPeriod(): int
    {
        return match ($this) {
            self::Quarterly => 3,
            self::SemiAnnual => 6,
            self::Annual => 12,
        };
    }

    public function periodsPerYear(): int
    {
        return intdiv(self::MONTHS_PER_YEAR, $this->monthsPerPeriod());
    }

    public function defaultNoticeDays(): int
    {
        return match ($this) {
            self::Quarterly => 14,
            self::SemiAnnual => 21,
            self::Annual => 30,
        };
    }

    /**
     * @return list<int>
     */
    public function defaultReminderDays(): array
    {
        return match ($this) {
            self::Quarterly => [14, 7, 1],
            self::SemiAnnual => [30, 14, 7, 1],
            self::Annual => [60, 30, 14, 7, 1],
        };
    }

    public function maxDays(): int
    {
        return match ($this) {
            self::Quarterly => 89,
            self::SemiAnnual => 180,
            self::Annual => 364,
        };
    }
}
