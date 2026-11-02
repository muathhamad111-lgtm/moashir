<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\CancellationCause;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentStatus;
use App\Events\Finance\PaymentCancelled;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;

final readonly class PaymentCancellation
{
    public const string VERB = 'cancel';

    public function __construct(private PaymentStateMachine $stateMachine, private AuditRecorder $audit) {}

    public function cancel(Payment $locked, CancellationCause $cause): void
    {
        $previous = $locked->status;

        if ($cause === CancellationCause::CheckoutUnavailable) {
            $locked->failure_reason = PaymentFailureReason::CheckoutUnavailable;
        }

        $this->stateMachine->transition($locked, PaymentStatus::Cancelled, self::VERB);

        $this->audit->record(new AuditEntry(AuditEvent::PaymentCancelled, $locked, ['status' => $previous->value], [
            'status' => PaymentStatus::Cancelled->value,
            'cause' => $cause->value,
        ]));

        PaymentCancelled::dispatch($locked, $cause);
    }
}
