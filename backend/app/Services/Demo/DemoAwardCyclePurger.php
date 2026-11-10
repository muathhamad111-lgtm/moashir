<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\AwardCycle;
use App\Support\ScalarValue;

final class DemoAwardCyclePurger
{
    public function purgeUnusedDraftsCreatedBy(int $userId): int
    {
        return ScalarValue::int(AwardCycle::query()
            ->where('created_by_user_id', $userId)
            ->where('status', AwardCycleStatus::Draft)
            ->whereDoesntHave('requests')
            ->whereDoesntHave('committeeMembers')
            ->toBase()
            ->delete());
    }
}
