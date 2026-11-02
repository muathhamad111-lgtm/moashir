<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;

final class PaymentPresenter
{
    /**
     * @return array{price_before_vat: string, vat_rate: string, vat_amount: string, total: string, currency: string}
     */
    public static function pricing(Payment $payment): array
    {
        return [
            'price_before_vat' => $payment->price_before_vat,
            'vat_rate' => $payment->vat_rate,
            'vat_amount' => $payment->vat_amount,
            'total' => $payment->total,
            'currency' => $payment->currency,
        ];
    }

    /**
     * @return array{id: string, number: string|null, status: string, is_test: bool, issued_at: string}|null
     */
    public static function receiptSummary(?PaymentReceipt $receipt): ?array
    {
        if (! $receipt instanceof PaymentReceipt) {
            return null;
        }

        return [
            'id' => $receipt->public_id,
            'number' => $receipt->number,
            'status' => $receipt->status->value,
            'is_test' => $receipt->is_test,
            'issued_at' => $receipt->issued_at->toIso8601String(),
        ];
    }

    public static function maskedReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        return mb_strlen($reference) <= 6 ? $reference : '…'.mb_substr($reference, -6);
    }
}
