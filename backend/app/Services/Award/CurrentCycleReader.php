<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\AwardCycle;

final class CurrentCycleReader
{
    public function running(): ?AwardCycle
    {
        return AwardCycle::query()->with('regions')->running()->latest('submission_start')->first();
    }
}
