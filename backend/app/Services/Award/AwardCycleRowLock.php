<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\AwardCycle;

final class AwardCycleRowLock
{
    public function lock(int $cycleId): AwardCycle
    {
        return AwardCycle::query()->with('regions')->whereKey($cycleId)->lockForUpdate()->firstOrFail();
    }
}
