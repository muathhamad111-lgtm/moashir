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
final class TransactionResource extends JsonResource
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
            'receipt_number' => $payment->receipt?->number,
            'purpose' => $payment->purpose->value,
            'channel' => $payment->channel->value,
            'status' => $payment->status->value,
            'is_test' => $payment->isTest(),
            'entity' => [
                'id' => $payment->entity_id,
                'reference' => $payment->entity->reference(),
                'name' => $payment->entity_name->toArray(),
                'email' => $payment->entity->owner?->email,
            ],
            'method' => $payment->method?->value,
            'card_last4' => $payment->card_last4,
            'pricing' => PaymentPresenter::pricing($payment),
            'late_paid' => $payment->late_paid_at !== null,
            'manually_settled' => $payment->manually_settled_at !== null,
            'has_refund' => $payment->refund !== null,
            'created_at' => $payment->created_at->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
            'updated_at' => $payment->updated_at->toIso8601String(),
        ];
    }
}
