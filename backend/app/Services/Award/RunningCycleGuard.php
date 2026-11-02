<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Exceptions\Award\AwardRejected;
use App\Models\Award\AwardCycle;

final class RunningCycleGuard
{
    public function assertNoOtherRunning(AwardCycle $cycle): void
    {
        $running = AwardCycle::query()->running()->whereKeyNot($cycle->id)->value('id');

        if (is_int($running)) {
            throw AwardRejected::anotherCycleRunning($running);
        }
    }
}
