<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BankAccount $resource
 */
final class TransferAccountResource extends JsonResource
{
    /**
     * @return array{id: string, bank_name: array{ar: string, en: string}, iban_last4: string, state: string}
     */
    public function toArray(Request $request): array
    {
        $account = $this->resource;

        return [
            'id' => $account->public_id,
            'bank_name' => $account->bank_name->toArray(),
            'iban_last4' => $account->ibanLastFour(),
            'state' => $account->state()->value,
        ];
    }
}
