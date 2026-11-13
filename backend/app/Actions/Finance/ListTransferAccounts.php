<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Finance\BankAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListTransferAccounts
{
    /**
     * @return LengthAwarePaginator<int, BankAccount>
     */
    public function handle(int $perPage): LengthAwarePaginator
    {
        return BankAccount::query()
            ->where(static fn (Builder $query): Builder => $query->whereNull('archived_at')->orWhereHas('transfers'))
            ->orderByRaw('archived_at asc nulls first')
            ->inDisplayOrder()
            ->paginate($perPage);
    }
}
