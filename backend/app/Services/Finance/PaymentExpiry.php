<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Integrations\PaymentGateway;
use App\Data\Integrations\ProviderPayment;
use App\Enums\Finance\CancellationCause;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class PaymentExpiry
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentOutcomeApplier $applier,
        private PaymentCancellation $cancellation,
        private PaymentRowLock $locks,
        private PaymentEventRecorder $events,
        private FinanceSettings $settings,
    ) {}

    public function expire(Payment $payment): void
    {
        if ($payment->status === PaymentStatus::Created) {
            $this->expireUnbound($payment);
        }

        if ($payment->status === PaymentStatus::Pending) {
            $this->expireBound($payment);
        }
    }

    private function expireUnbound(Payment $payment): void
    {
        if ($payment->provider_intent_id !== null && ! $this->cancelIntent($payment, $payment->provider_intent_id)) {
            return;
        }

        $this->cancelIf($payment, PaymentStatus::Created);
    }

    private function expireBound(Payment $payment): void
    {
        $provider = $this->fetch($payment);

        if ($provider instanceof ProviderPayment) {
            $this->applier->apply($payment, $provider, PaymentEventSource::Expiry);
        }

        $graceEndsAt = $payment->expires_at->copy()->addMinutes($this->settings->pendingGraceMinutes());

        if ($payment->status === PaymentStatus::Pending && Date::now()->greaterThanOrEqualTo($graceEndsAt)) {
            $this->cancelIf($payment, PaymentStatus::Pending);
        }
    }

    private function cancelIntent(Payment $payment, string $intentId): bool
    {
        try {
            $this->gateway->cancelCheckout($payment->mode, $intentId);
        } catch (ProviderCallFailed $failure) {
            if ($failure->isTransient()) {
                return false;
            }

            report($failure);
        }

        return true;
    }

    private function fetch(Payment $payment): ?ProviderPayment
    {
        if ($payment->provider_payment_id === null) {
            return null;
        }

        try {
            return $this->gateway->fetchPayment($payment->mode, $payment->provider_payment_id);
        } catch (ProviderCallFailed $failure) {
            if (! $failure->isTransient()) {
                report($failure);
            }

            return null;
        }
    }

    private function cancelIf(Payment $payment, PaymentStatus $expected): void
    {
        DB::transaction(function () use ($payment, $expected): void {
            $locked = $this->locks->lock($payment);

            if ($locked->status !== $expected) {
                return;
            }

            $this->cancellation->cancel($locked, CancellationCause::Expired);
            $this->events->recordExpiry($locked, 'order_expired');
        });

        $payment->refresh();
    }
}
