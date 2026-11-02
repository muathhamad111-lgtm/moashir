<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Integrations\PaymentGateway;
use App\Data\Audit\AuditEntry;
use App\Data\Finance\VerificationOutcome;
use App\Data\Integrations\ProviderPayment;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\PaymentOutcomeApplier;

final readonly class VerifyPayment
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentOutcomeApplier $applier,
        private AuditRecorder $audit,
    ) {}

    public function handle(Payment $payment, string $providerPaymentId): VerificationOutcome
    {
        if ($payment->isBound() && $payment->provider_payment_id !== $providerPaymentId) {
            $this->rejected($payment, 'binding_mismatch');

            throw PaymentRejected::bindingMismatch();
        }

        if ($payment->status->isConcluded()) {
            return new VerificationOutcome($payment, PaymentEventResult::Ignored);
        }

        $result = $this->applier->apply($payment, $this->fetch($payment, $providerPaymentId), PaymentEventSource::Verification);

        if ($result === PaymentEventResult::Rejected) {
            $this->rejected($payment, 'foreign_payment');

            throw PaymentRejected::verificationRejected();
        }

        return new VerificationOutcome($payment->load('receipt'), $result);
    }

    private function fetch(Payment $payment, string $providerPaymentId): ProviderPayment
    {
        try {
            return $this->gateway->fetchPayment($payment->mode, $providerPaymentId);
        } catch (ProviderCallFailed $failure) {
            if ($failure->kind === ProviderErrorKind::Rejected) {
                throw PaymentRejected::providerPaymentNotFound();
            }

            throw $failure;
        }
    }

    private function rejected(Payment $payment, string $reason): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::PaymentVerificationRejected, $payment, [], ['reason' => $reason, 'status' => $payment->status->value]));
    }
}
