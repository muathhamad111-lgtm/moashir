<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Integrations\ProviderPayment;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ProviderPaymentStatus;
use App\Enums\Finance\ProviderStatusEffect;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\DB;

final readonly class PaymentOutcomeApplier
{
    public function __construct(
        private PaymentRowLock $locks,
        private ProviderPaymentMatcher $matcher,
        private PaymentBinder $binder,
        private PaymentSettlement $settlement,
        private PaymentFailure $failure,
        private LatePaymentRecorder $latePayments,
        private PaymentAlerts $alerts,
        private PaymentEventRecorder $events,
    ) {}

    public function apply(Payment $payment, ProviderPayment $provider, PaymentEventSource $source): PaymentEventResult
    {
        if ($payment->isBankTransfer()) {
            return $this->refuseForBankTransfer($payment, $provider, $source);
        }

        $result = $this->matcher->belongsTo($payment, $provider)
            ? DB::transaction(fn (): PaymentEventResult => $this->applyLocked($this->locks->lock($payment), $provider))
            : PaymentEventResult::Rejected;

        if ($result === PaymentEventResult::Rejected) {
            $this->alerts->serviceIssue('verification_rejected');
        }

        if ($source !== PaymentEventSource::Webhook) {
            $this->events->recordProviderFetch($payment, $provider, $source, $result);
        }

        $payment->refresh();

        return $result;
    }

    private function refuseForBankTransfer(Payment $payment, ProviderPayment $provider, PaymentEventSource $source): PaymentEventResult
    {
        $this->alerts->serviceIssue('verification_rejected');

        if (ProviderPaymentStatus::effectOf($provider->status) === ProviderStatusEffect::Settle) {
            $this->alerts->refundRequired($payment);
        }

        if ($source !== PaymentEventSource::Webhook) {
            $this->events->recordProviderFetch($payment, $provider, $source, PaymentEventResult::Rejected);
        }

        return PaymentEventResult::Rejected;
    }

    private function applyLocked(Payment $locked, ProviderPayment $provider): PaymentEventResult
    {
        if ($locked->isBound() && $locked->provider_payment_id !== $provider->id) {
            return PaymentEventResult::Rejected;
        }

        $effect = ProviderPaymentStatus::effectOf($provider->status);

        return match (true) {
            $locked->status === PaymentStatus::Cancelled => $this->afterCancellation($locked, $provider, $effect),
            $locked->status->isOpen() => $this->whileOpen($locked, $provider, $effect),
            default => $this->afterCompletion($locked, $effect),
        };
    }

    private function whileOpen(Payment $locked, ProviderPayment $provider, ProviderStatusEffect $effect): PaymentEventResult
    {
        return match ($effect) {
            ProviderStatusEffect::AwaitPayment => $this->awaitPayment($locked, $provider),
            ProviderStatusEffect::Settle => $this->settle($locked, $provider),
            ProviderStatusEffect::Fail => $this->fail($locked, $provider),
            ProviderStatusEffect::ReportRefund => $this->reportRefund($locked),
            ProviderStatusEffect::Unexpected => $this->unexpected(),
        };
    }

    private function afterCancellation(Payment $locked, ProviderPayment $provider, ProviderStatusEffect $effect): PaymentEventResult
    {
        if ($effect !== ProviderStatusEffect::Settle) {
            return PaymentEventResult::Ignored;
        }

        if ($this->latePayments->record($locked, $provider)) {
            $this->alerts->refundRequired($locked);
        }

        return PaymentEventResult::LatePayment;
    }

    private function afterCompletion(Payment $locked, ProviderStatusEffect $effect): PaymentEventResult
    {
        if ($effect === ProviderStatusEffect::ReportRefund && $locked->status === PaymentStatus::Paid) {
            $this->alerts->providerRefundReported($locked);
        }

        return PaymentEventResult::Ignored;
    }

    private function awaitPayment(Payment $locked, ProviderPayment $provider): PaymentEventResult
    {
        $this->binder->bind($locked, $provider->id);

        return PaymentEventResult::Pending;
    }

    private function settle(Payment $locked, ProviderPayment $provider): PaymentEventResult
    {
        $this->binder->bind($locked, $provider->id);

        if (! $this->matcher->amountMatches($locked, $provider)) {
            $this->failure->markFailed($locked, PaymentFailureReason::AmountMismatch, $provider->responseCode);
            $this->alerts->refundRequired($locked);

            return PaymentEventResult::Applied;
        }

        $this->settlement->markPaid($locked, PaymentInstrument::fromSource($provider->sourceType, $provider->cardCompany), $provider->cardLast4);

        return PaymentEventResult::Applied;
    }

    private function fail(Payment $locked, ProviderPayment $provider): PaymentEventResult
    {
        $this->binder->bind($locked, $provider->id);
        $this->failure->markFailed($locked, PaymentFailureReason::fromResponseCode($provider->responseCode), $provider->responseCode, $provider->message);

        return PaymentEventResult::Applied;
    }

    private function reportRefund(Payment $locked): PaymentEventResult
    {
        $this->alerts->providerRefundReported($locked);

        return PaymentEventResult::Ignored;
    }

    private function unexpected(): PaymentEventResult
    {
        $this->alerts->serviceIssue('unexpected_status');

        return PaymentEventResult::Ignored;
    }
}
