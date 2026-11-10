<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Enums\Identity\PermissionKey;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;

final class RequestPaymentPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function of(?Payment $payment, mixed $viewer): ?array
    {
        if (! $payment instanceof Payment) {
            return null;
        }

        $linksFinance = self::linksFinance($viewer);
        $summary = [
            'reference' => $payment->reference,
            'channel' => $payment->channel->value,
            'status' => $payment->status->value,
            'is_test' => $payment->isTest(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'receipt' => self::receiptOf($payment->receipt, $linksFinance),
        ];

        return $linksFinance ? [...$summary, 'id' => $payment->public_id] : $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function receiptOf(?PaymentReceipt $receipt, bool $linksFinance): ?array
    {
        if (! $receipt instanceof PaymentReceipt) {
            return null;
        }

        $summary = ['number' => $receipt->number, 'status' => $receipt->status->value, 'is_test' => $receipt->is_test];

        return $linksFinance ? [...$summary, 'id' => $receipt->public_id] : $summary;
    }

    private static function linksFinance(mixed $viewer): bool
    {
        return $viewer instanceof User && $viewer->can(PermissionKey::FinanceOperationsView->value);
    }
}
