<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class PaymentBinder
{
    public const string VERB = 'bind';

    public function __construct(private PaymentStateMachine $stateMachine, private AuditRecorder $audit) {}

    public function bind(Payment $locked, string $providerPaymentId): void
    {
        if ($locked->provider_payment_id === $providerPaymentId) {
            return;
        }

        if ($locked->isBound()) {
            throw PaymentRejected::bindingMismatch();
        }

        if (Payment::query()->where('provider_payment_id', $providerPaymentId)->exists()) {
            throw PaymentRejected::providerPaymentIdInUse();
        }

        $previous = $locked->status;
        $locked->provider_payment_id = $providerPaymentId;
        $locked->bound_at = Date::now();

        if ($locked->status === PaymentStatus::Created) {
            $this->stateMachine->transition($locked, PaymentStatus::Pending, self::VERB);
        } else {
            $locked->save();
        }

        $this->audit->record(new AuditEntry(AuditEvent::PaymentBound, $locked, ['status' => $previous->value], [
            'status' => $locked->status->value,
            'provider_payment_id' => $providerPaymentId,
        ]));
    }
}
