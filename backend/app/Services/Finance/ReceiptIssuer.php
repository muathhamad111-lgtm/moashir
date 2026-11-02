<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\ReceiptStatus;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class ReceiptIssuer
{
    public function __construct(
        private ReceiptNumberGenerator $numbers,
        private ReceiptSnapshotBuilder $snapshots,
        private AuditRecorder $audit,
    ) {}

    public function issue(Payment $paid): PaymentReceipt
    {
        $issuedAt = Date::now();
        $receipt = PaymentReceipt::query()->create([
            'payment_id' => $paid->id,
            'number' => $paid->isTest() ? null : $this->numbers->next($issuedAt),
            'is_test' => $paid->isTest(),
            'status' => ReceiptStatus::Issued,
            'issued_at' => $issuedAt,
            'snapshot' => $this->snapshots->build($paid),
        ]);
        $receipt->setRelation('payment', $paid);

        $this->audit->record(new AuditEntry(AuditEvent::ReceiptIssued, $receipt, [], [
            'number' => $receipt->number,
            'is_test' => $receipt->is_test,
            'payment_reference' => $paid->reference,
        ]));

        return $receipt;
    }
}
