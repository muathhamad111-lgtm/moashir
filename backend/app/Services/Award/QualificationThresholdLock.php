<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\FilteringDecision;

/**
 * The qualification threshold of a cycle freezes with its first filtering decision (operator, 2026-11-22): decisions
 * may be taken while submissions are open, and a later change would leave earlier decisions judged against another
 * threshold, a qualification below the new threshold without its mandatory reason among them (03 §8.6, Q-09).
 */
final class QualificationThresholdLock
{
    public function isLocked(int $cycleId): bool
    {
        return FilteringDecision::query()->where('award_cycle_id', $cycleId)->exists();
    }
}
