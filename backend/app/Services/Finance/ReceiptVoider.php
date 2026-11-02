<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\ReceiptStatus;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class ReceiptVoider
{
    public function __construct(private AuditRecorder $audit) {}

    public function voidFor(Payment $payment, Refund $refund): void
    {
        $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->lockForUpdate()->first();

        if (! $receipt instanceof PaymentReceipt || $receipt->isVoided()) {
            return;
        }

        $receipt->forceFill([
            'status' => ReceiptStatus::Voided,
            'voided_at' => Date::now(),
            'void_reason' => $refund->reason,
            'voided_by_refund_id' => $refund->id,
            'pdf_file_id' => null,
        ])->save();

        $this->audit->record(new AuditEntry(AuditEvent::ReceiptVoided, $receipt, ['status' => ReceiptStatus::Issued->value], [
            'status' => ReceiptStatus::Voided->value,
            'number' => $receipt->number,
            'voided_by_refund_id' => $refund->id,
        ]));
    }
}
