<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\CheckoutOptions;
use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentChannel;
use App\Models\Finance\BankAccount;
use App\Support\Paths\PathPricingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CheckoutOptions $resource
 */
final class CheckoutOptionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'channels' => array_map(static fn (PaymentChannel $channel): string => $channel->value, $this->resource->channels),
            'bank_accounts' => array_map(static fn (BankAccount $account): array => new PayerBankAccountResource($account)->toArray($request), $this->resource->bankAccounts),
            'transfer_note' => ['unified_number' => $this->resource->unifiedNumber],
            'pricing' => $this->resource->pricing instanceof PathPricing ? PathPricingPresenter::present($this->resource->pricing) : null,
        ];
    }
}
