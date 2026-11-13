<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\TransferQueueCounts;
use App\Models\Finance\BankTransfer;
use App\Services\Entities\EntityFreezeGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;

final readonly class BankTransferQueueCounter
{
    public function __construct(
        private BankTransferQuery $transfers,
        private EntityFreezeGuard $freezeGuard,
        private Repository $config,
    ) {}

    public function count(): TransferQueueCounts
    {
        $today = CarbonImmutable::now($this->config->string('app.display_timezone'))->startOfDay();

        return new TransferQueueCounts(
            $this->transfers->awaitingReviewCount(),
            $this->decidedOn($today)->whereNull('rejection_reason')->count(),
            $this->decidedOn($today)->whereNotNull('rejection_reason')->count(),
        );
    }

    /**
     * @return Builder<BankTransfer>
     */
    private function decidedOn(CarbonImmutable $day): Builder
    {
        return BankTransfer::query()
            ->where('reviewed_at', '>=', $day->utc())
            ->where('reviewed_at', '<', $day->addDay()->utc())
            ->whereHas('payment', fn (Builder $payment): Builder => $this->freezeGuard->excludeFrozen($payment));
    }
}
