<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentStatus;
use App\Events\Finance\PaymentPaid;
use App\Events\Finance\ReceiptIssued;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class PaymentSettlement
{
    public const string VERB = 'settle';

    public function __construct(
        private PaymentStateMachine $stateMachine,
        private ReceiptIssuer $receipts,
        private CheckoutIntentTracker $intents,
        private AuditRecorder $audit,
    ) {}

    public function markPaid(Payment $locked, ?PaymentInstrument $method, ?string $cardLast4): void
    {
        $this->stateMachine->assertAllows($locked, PaymentStatus::Paid, self::VERB);

        $locked->forceFill(['method' => $method, 'card_last4' => $cardLast4, 'verified_at' => Date::now()]);
        $this->stateMachine->transition($locked, PaymentStatus::Paid, self::VERB);

        $receipt = $this->receipts->issue($locked);
        $this->intents->convert($locked);

        $this->audit->record(new AuditEntry(AuditEvent::PaymentPaid, $locked, ['status' => PaymentStatus::Pending->value], [
            'status' => PaymentStatus::Paid->value,
            'method' => $method?->value,
            'total' => $locked->total,
            'receipt_number' => $receipt->number,
            'is_test' => $locked->isTest(),
        ]));

        PaymentPaid::dispatch($locked);
        ReceiptIssued::dispatch($receipt);
    }
}
