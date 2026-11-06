<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\PaymentReceipt;
use App\Services\Finance\ReceiptSnapshotBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaymentReceipt $resource
 */
final class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $receipt = $this->resource;

        return [
            'id' => $receipt->public_id,
            'number' => $receipt->number,
            'status' => $receipt->status->value,
            'is_test' => $receipt->is_test,
            'issued_at' => $receipt->issued_at->toIso8601String(),
            'voided_at' => $receipt->voided_at?->toIso8601String(),
            'reissue_count' => $receipt->reissue_count,
            'last_reissued_at' => $receipt->last_reissued_at?->toIso8601String(),
            'vat_registration_number' => $receipt->snapshot[ReceiptSnapshotBuilder::VAT_REGISTRATION_NUMBER] ?? null,
            'snapshot' => $receipt->snapshot,
        ];
    }
}
