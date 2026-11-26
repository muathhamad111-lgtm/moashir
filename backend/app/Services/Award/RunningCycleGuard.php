<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CycleOpenBlock;
use App\Enums\Award\AwardCycleStatus;
use App\Exceptions\Award\CycleStartBlocked;
use App\Models\Award\AwardCycle;
use App\Services\Platform\TransactionLock;

final readonly class RunningCycleGuard
{
    public const string LOCK = 'award_cycles:start';

    public function __construct(private TransactionLock $lock, private AwardCycleConcurrencyReader $concurrency) {}

    public function assertMayStart(AwardCycle $cycle, AwardCycleStatus $target): void
    {
        $this->lock->lock(self::LOCK);
        $block = $this->blockerFor($cycle, $target);

        if ($block instanceof CycleOpenBlock) {
            throw CycleStartBlocked::by($block);
        }
    }

    public function blockerFor(AwardCycle $cycle, AwardCycleStatus $target): ?CycleOpenBlock
    {
        $concurrency = $this->concurrency->read();
        $statuses = $concurrency->blockingStatuses($target);

        if ($statuses === []) {
            return null;
        }

        $blocking = AwardCycle::query()->whereIn('status', $statuses)->whereKeyNot($cycle->id)->latest('submission_start')->orderByDesc('id')->first();

        return $blocking instanceof AwardCycle ? new CycleOpenBlock($concurrency, $blocking) : null;
    }
}
