<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Support\Finance\StaffTransferPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Payment $resource
 */
final class TransactionDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;

        return [
            ...new TransactionResource($payment)->toArray($request),
            'provider_payment_id' => $payment->provider_payment_id,
            'failure_reason' => $payment->failure_reason?->value,
            'failure_code' => $payment->failure_code,
            'failure_message' => $payment->failure_message,
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'verified_at' => $payment->verified_at?->toIso8601String(),
            'failed_at' => $payment->failed_at?->toIso8601String(),
            'cancelled_at' => $payment->cancelled_at?->toIso8601String(),
            'late_paid_at' => $payment->late_paid_at?->toIso8601String(),
            'manually_settled_at' => $payment->manually_settled_at?->toIso8601String(),
            'receipt' => $payment->receipt instanceof PaymentReceipt ? new ReceiptResource($payment->receipt)->toArray($request) : null,
            'refund' => $payment->refund instanceof Refund ? new RefundResource($payment->refund)->toArray($request) : null,
            'rejected_at' => $payment->rejected_at?->toIso8601String(),
            'bank_transfer' => $payment->bankTransfer instanceof BankTransfer ? StaffTransferPresenter::detailsOf($payment->bankTransfer) : null,
        ];
    }
}
