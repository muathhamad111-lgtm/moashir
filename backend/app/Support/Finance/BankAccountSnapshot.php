<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Models\Finance\BankAccount;

final class BankAccountSnapshot
{
    /**
     * @return array{bank_name: array{ar: string, en: string}, account_holder_name: array{ar: string, en: string}, iban: string, account_number: string|null, is_active: bool, is_archived: bool}
     */
    public static function of(BankAccount $account): array
    {
        return [
            'bank_name' => $account->bank_name->toArray(),
            'account_holder_name' => $account->account_holder_name->toArray(),
            'iban' => $account->iban,
            'account_number' => $account->account_number,
            'is_active' => $account->is_active,
            'is_archived' => $account->archived_at !== null,
        ];
    }
}
