<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Services\Periods\PeriodSchedule;

final readonly class GeneratePeriods
{
    public function __construct(private PeriodSchedule $schedule) {}

    /**
     * @return list<int>
     */
    public function handle(): array
    {
        return $this->schedule->sync();
    }
}
