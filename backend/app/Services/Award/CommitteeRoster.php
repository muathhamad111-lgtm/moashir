<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\CycleCommitteeAssessor;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Collection;

final class CommitteeRoster
{
    /**
     * @return Collection<int, CycleCommitteeAssessor>
     */
    public function activeMembers(int $cycleId): Collection
    {
        return CycleCommitteeAssessor::query()->where('award_cycle_id', $cycleId)->active()->orderBy('id')->get();
    }

    public function seatOf(int $cycleId, int $userId): ?CycleCommitteeAssessor
    {
        return CycleCommitteeAssessor::query()->where('award_cycle_id', $cycleId)->where('assessor_user_id', $userId)->seated()->first();
    }

    public function hasOtherActive(int $cycleId, int $exceptMemberId): bool
    {
        return CycleCommitteeAssessor::query()->where('award_cycle_id', $cycleId)->active()->whereKeyNot($exceptMemberId)->exists();
    }

    /**
     * @return list<int>
     */
    public function activeUserIds(int $cycleId): array
    {
        return array_values(CycleCommitteeAssessor::query()
            ->where('award_cycle_id', $cycleId)
            ->active()
            ->orderBy('id')
            ->pluck('assessor_user_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }

    public function lock(int $memberId): CycleCommitteeAssessor
    {
        return CycleCommitteeAssessor::query()->whereKey($memberId)->lockForUpdate()->firstOrFail();
    }
}
