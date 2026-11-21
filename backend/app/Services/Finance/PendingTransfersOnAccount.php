<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PendingTransfersOnAccount
{
    /**
     * @return Collection<int, Payment>
     */
    public function of(BankAccount $account): Collection
    {
        return Payment::query()
            ->bankTransfers()
            ->where('status', PaymentStatus::UnderReview)
            ->whereHas('bankTransfer', static fn (Builder $transfer): Builder => $transfer->where('bank_account_id', $account->id))
            ->with('bankTransfer')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function countOf(BankAccount $account): int
    {
        return Payment::query()
            ->bankTransfers()
            ->where('status', PaymentStatus::UnderReview)
            ->whereHas('bankTransfer', static fn (Builder $transfer): Builder => $transfer->where('bank_account_id', $account->id))
            ->count();
    }
}
