<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Refund $resource
 */
final class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $refund = $this->resource;
        $evidence = $refund->evidenceFile;

        return [
            'id' => $refund->public_id,
            'payment' => ['id' => $refund->payment->public_id, 'reference' => $refund->payment->reference],
            'status' => $refund->status->value,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'refunded_on' => $refund->refunded_on->toDateString(),
            'transfer_reference' => $refund->transfer_reference,
            'reason' => $refund->reason,
            'recorded_by' => ['id' => $refund->recorded_by_user_id, 'name' => $refund->recordedBy->name],
            'evidence' => [
                'id' => $evidence->public_id,
                'name' => $evidence->original_name,
                'mime_type' => $evidence->mime_type,
                'size_bytes' => $evidence->size_bytes,
                'scan_status' => $evidence->malware_scan_status->value,
            ],
            'created_at' => $refund->created_at->toIso8601String(),
        ];
    }
}
