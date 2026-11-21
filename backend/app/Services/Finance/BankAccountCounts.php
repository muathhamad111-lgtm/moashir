<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\BankTransfer;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final class BankAccountCounts
{
    /**
     * @return array<int|string, string|Closure(Builder<BankTransfer>): void>
     */
    public static function relations(): array
    {
        return [
            'transfers',
            'transfers as pending_transfers_count' => static function (Builder $transfers): void {
                $transfers->whereHas('payment', static fn (Builder $payment): Builder => $payment->where('status', PaymentStatus::UnderReview));
            },
        ];
    }
}
