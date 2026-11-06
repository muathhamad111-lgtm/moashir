<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\BankAccount;

final class BankAccountRowLock
{
    public function lock(BankAccount $account): BankAccount
    {
        return BankAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }
}
