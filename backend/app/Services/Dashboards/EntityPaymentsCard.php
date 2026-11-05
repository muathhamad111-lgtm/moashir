<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Finance\ReceiptStatus;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Support\Dashboards\DashboardLinks;
use Illuminate\Database\Eloquent\Builder;

final class EntityPaymentsCard
{
    /**
     * @return array<string, mixed>
     */
    public function of(int $entityId): array
    {
        $pending = $this->payments($entityId)->open()->orderBy('expires_at')->first();
        $last = $this->payments($entityId)->latest('created_at')->latest('id')->first();

        return [
            'last_payment' => $last instanceof Payment ? $this->present($last) : null,
            'pending_payment' => $pending instanceof Payment ? [
                ...$this->present($pending),
                'expires_at' => $pending->expires_at->toIso8601String(),
            ] : null,
            'receipts_count' => PaymentReceipt::query()
                ->where('status', ReceiptStatus::Issued)
                ->whereIn('payment_id', $this->payments($entityId)->live()->select('id'))
                ->count(),
            'link' => DashboardLinks::MY_PAYMENTS,
        ];
    }

    /**
     * @return Builder<Payment>
     */
    private function payments(int $entityId): Builder
    {
        return Payment::query()->ownedBy($entityId);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Payment $payment): array
    {
        return [
            'id' => $payment->public_id,
            'reference' => $payment->reference,
            'purpose' => $payment->purpose->value,
            'status' => $payment->status->value,
            'total' => $payment->total,
            'currency' => $payment->currency,
            'created_at' => $payment->created_at->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }
}
