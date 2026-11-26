<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Finance\BankAccount;
use App\Services\Finance\BankAccountCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListBankAccounts
{
    /**
     * @return LengthAwarePaginator<int, BankAccount>
     */
    public function handle(?bool $active, int $perPage): LengthAwarePaginator
    {
        return BankAccount::query()
            ->listed()
            ->with('createdBy')
            ->withCount(BankAccountCounts::relations())
            ->when($active !== null, static fn (Builder $query): Builder => $query->where('is_active', $active))
            ->inDisplayOrder()
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, BankAccount>
     */
    public function all(): Collection
    {
        return BankAccount::query()->listed()->with('createdBy')->withCount(BankAccountCounts::relations())->inDisplayOrder()->get();
    }

    public function present(BankAccount $account): BankAccount
    {
        return $account->load('createdBy')->loadCount(BankAccountCounts::relations());
    }
}
