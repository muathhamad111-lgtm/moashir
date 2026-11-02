<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Models\Finance\Payment;
use App\Support\Finance\PaymentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Payment $resource
 */
final class OwnPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;

        return [
            'id' => $payment->public_id,
            'reference' => $payment->reference,
            'purpose' => $payment->purpose->value,
            'status' => $payment->status->value,
            'is_test' => $payment->isTest(),
            'pricing' => PaymentPresenter::pricing($payment),
            'method' => $payment->method?->value,
            'card_last4' => $payment->card_last4,
            'failure_reason' => $payment->failure_reason?->value,
            'expires_at' => $payment->expires_at->toIso8601String(),
            'created_at' => $payment->created_at->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'failed_at' => $payment->failed_at?->toIso8601String(),
            'cancelled_at' => $payment->cancelled_at?->toIso8601String(),
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
            'receipt' => PaymentPresenter::receiptSummary($payment->receipt),
        ];
    }
}
