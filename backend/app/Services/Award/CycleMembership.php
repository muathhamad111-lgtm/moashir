<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CycleMemberRole;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Award\AwardCycleMember;
use App\Models\Award\CycleCommitteeAssessor;
use App\Support\ScalarValue;

final class CycleMembership
{
    public function isMember(int $userId, int $cycleId, CycleMemberRole ...$roles): bool
    {
        $query = AwardCycleMember::query()->where('award_cycle_id', $cycleId)->where('user_id', $userId);

        if ($roles !== []) {
            $query->whereIn('role', array_map(static fn (CycleMemberRole $role): string => $role->value, $roles));
        }

        return $query->exists();
    }

    public function isInitialAssessor(int $userId, int $cycleId): bool
    {
        return AwardCycleAssessor::query()->where('award_cycle_id', $cycleId)->where('assessor_user_id', $userId)->active()->exists();
    }

    public function isCommitteeAssessor(int $userId, int $cycleId): bool
    {
        return CycleCommitteeAssessor::query()->where('award_cycle_id', $cycleId)->where('assessor_user_id', $userId)->seated()->exists();
    }

    /**
     * @return list<int>
     */
    public function memberIdsOf(int $cycleId, CycleMemberRole $role): array
    {
        return array_values(AwardCycleMember::query()
            ->where('award_cycle_id', $cycleId)
            ->where('role', $role)
            ->orderBy('id')
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }
}
