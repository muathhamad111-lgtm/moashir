<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\AwardCycleAssessor;

/**
 * Whether a cycle has an initial assessor to assign: the Follower can only assign the cycle's linked initial
 * assessors (03 §8.1, §8.2a), so a cycle without one cannot take its requests forward.
 */
final class InitialAssessorLinks
{
    public function hasActive(int $cycleId): bool
    {
        return AwardCycleAssessor::query()->where('award_cycle_id', $cycleId)->active()->exists();
    }
}
