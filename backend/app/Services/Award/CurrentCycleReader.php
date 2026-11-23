<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\AwardCycle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final class CurrentCycleReader
{
    public function running(): ?AwardCycle
    {
        return $this->openForSubmission()->first()
            ?? AwardCycle::query()->with('regions')->running()->latest('submission_start')->orderByDesc('id')->first();
    }

    public function runningById(int $cycleId): ?AwardCycle
    {
        return AwardCycle::query()->with('regions')->running()->whereKey($cycleId)->first();
    }

    /**
     * @return Collection<int, AwardCycle>
     */
    public function openForSubmission(): Collection
    {
        $now = Date::now();

        return AwardCycle::query()
            ->with('regions')
            ->where('status', AwardCycleStatus::SubmissionOpen)
            ->where('submission_start', '<=', $now)
            ->where('submission_end', '>=', $now)
            ->latest('submission_start')
            ->orderByDesc('id')
            ->get();
    }
}
