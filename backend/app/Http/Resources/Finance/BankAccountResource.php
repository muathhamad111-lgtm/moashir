<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\BankAccount;
use App\Support\Finance\BankTransferPresenter;
use App\Support\ScalarValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BankAccount $resource
 */
final class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->resource;
        $creator = $account->createdBy;

        return [
            ...BankTransferPresenter::payeeOf($account),
            'account_number' => $account->account_number,
            'payer_note' => $account->payer_note?->toArray(),
            'display_order' => $account->display_order,
            'is_active' => $account->is_active,
            'is_used' => ScalarValue::int($account->getAttribute('transfers_count')) > 0,
            'pending_transfers_count' => ScalarValue::int($account->getAttribute('pending_transfers_count')),
            'activated_at' => $account->activated_at->toIso8601String(),
            'deactivated_at' => $account->deactivated_at?->toIso8601String(),
            'created_at' => $account->created_at->toIso8601String(),
            'created_by' => ['id' => $creator->id, 'name' => $creator->isSuperAdmin() ? null : $creator->name],
        ];
    }
}
