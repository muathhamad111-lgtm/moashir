<?php

declare(strict_types=1);

namespace App\Events\Finance;

use App\Enums\Finance\BankAccountChange;
use App\Models\Finance\BankAccount;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class BankAccountsChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public BankAccount $account, public BankAccountChange $change, public int $actorId) {}
}
