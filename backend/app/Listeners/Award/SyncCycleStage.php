<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Events\Award\CycleProgressChanged;
use App\Events\Award\EntityNotQualified;
use App\Services\Award\CycleStageSync;

final readonly class SyncCycleStage
{
    public function __construct(private CycleStageSync $stages) {}

    public function handle(CycleProgressChanged|EntityNotQualified $event): void
    {
        $this->stages->sync($event instanceof CycleProgressChanged ? $event->cycleId : (int) $event->assessment->award_cycle_id);
    }
}
