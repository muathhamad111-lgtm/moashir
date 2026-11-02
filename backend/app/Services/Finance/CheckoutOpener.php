<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Integrations\PaymentGateway;
use App\Data\Audit\AuditEntry;
use App\Data\Integrations\CheckoutSession;
use App\Data\Integrations\PaymentCheckoutRequest;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use App\Support\Integrations\IntegrationUrls;
use Illuminate\Support\Facades\DB;

final readonly class CheckoutOpener
{
    public function __construct(
        private PaymentGateway $gateway,
        private CheckoutPresenter $presenter,
        private IntegrationUrls $urls,
        private PaymentRowLock $locks,
        private PaymentEventRecorder $events,
        private AuditRecorder $audit,
    ) {}

    public function open(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Created || $payment->provider_intent_id !== null) {
            return;
        }

        $session = $this->gateway->createCheckout(new PaymentCheckoutRequest(
            $payment->mode,
            $payment->totalMoney(),
            CheckoutPresenter::descriptionOf($payment),
            $this->urls->paymentCallback($payment->public_id),
            $this->presenter->metadataOf($payment),
        ));

        if (! DB::transaction(fn (): bool => $this->attach($payment, $session))) {
            $this->discard($session);
        }

        $payment->refresh();
    }

    private function attach(Payment $payment, CheckoutSession $session): bool
    {
        $locked = $this->locks->lock($payment);

        if ($locked->status !== PaymentStatus::Created || $locked->provider_intent_id !== null) {
            return false;
        }

        $locked->forceFill([
            'provider_intent_id' => $session->intentId,
            'checkout_client_secret' => $session->clientSecret->reveal(),
        ])->save();

        $this->events->recordCheckout($locked, $session->intentId);
        $this->audit->record(new AuditEntry(AuditEvent::PaymentCheckoutOpened, $locked, [], [
            'provider_intent_id' => $session->intentId,
            'mode' => $locked->mode->value,
        ]));

        return true;
    }

    private function discard(CheckoutSession $session): void
    {
        try {
            $this->gateway->cancelCheckout($session->mode, $session->intentId);
        } catch (ProviderCallFailed $failure) {
            report($failure);
        }
    }
}
