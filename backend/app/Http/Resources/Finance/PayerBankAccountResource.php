<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\BankAccount;
use App\Support\Finance\BankTransferPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BankAccount $resource
 */
final class PayerBankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...BankTransferPresenter::payeeOf($this->resource),
            'account_number' => $this->resource->account_number,
            'payer_note' => $this->resource->payer_note?->toArray(),
        ];
    }
}
