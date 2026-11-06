<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\Finance\BankAccountRejected;
use App\Models\Finance\BankAccount;
use Illuminate\Contracts\Config\Repository;

final readonly class BankAccountLimit
{
    public function __construct(private Repository $config) {}

    public function assertRoomForOneMore(): void
    {
        $max = $this->config->integer('moashir.finance.bank_transfer.max_active_accounts');

        if (BankAccount::query()->selectable()->count() >= $max) {
            throw BankAccountRejected::limitReached($max);
        }
    }
}
