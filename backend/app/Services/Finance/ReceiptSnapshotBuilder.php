<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Payment;

final class ReceiptSnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Payment $payment): array
    {
        $payment->loadMissing('bankTransfer.bankAccount');

        return [
            'payment_reference' => $payment->reference,
            'entity_reference' => $payment->entity->reference(),
            'entity_name' => $payment->entity_name->toArray(),
            'purpose' => $payment->purpose->value,
            'price_before_vat' => $payment->price_before_vat,
            'vat_rate' => $payment->vat_rate,
            'vat_amount' => $payment->vat_amount,
            'total' => $payment->total,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'channel' => $payment->channel->value,
            'bank_name' => $payment->bankTransfer?->bankAccount->bank_name->toArray(),
            'method' => $payment->method?->value,
            'card_last4' => $payment->card_last4,
            'provider_payment_id' => $payment->provider_payment_id,
        ];
    }
}
