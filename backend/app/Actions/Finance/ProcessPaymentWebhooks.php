<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Integrations\PaymentGateway;
use App\Data\Integrations\ProviderPayment;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Services\Finance\PaymentEventRecorder;
use App\Services\Finance\PaymentOutcomeApplier;
use Illuminate\Database\Eloquent\Collection;

final readonly class ProcessPaymentWebhooks
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentOutcomeApplier $applier,
        private PaymentEventRecorder $events,
    ) {}

    public function handle(int $paymentId): void
    {
        $payment = Payment::query()->find($paymentId);

        if (! $payment instanceof Payment) {
            return;
        }

        $pending = $this->unprocessedEventsOf($payment);
        $foreign = $pending->filter(static fn (PaymentEvent $event): bool => $event->mode !== null && $event->mode !== $payment->mode);
        $this->events->markProcessed($this->idsOf($foreign), PaymentEventResult::Rejected, 'mode_mismatch');
        $events = $pending->diff($foreign);

        if ($events->isEmpty()) {
            return;
        }

        $provider = $this->fetch($payment, $events);

        if ($provider instanceof ProviderPayment) {
            $this->events->markProcessed($this->idsOf($events), $this->applier->apply($payment, $provider, PaymentEventSource::Webhook));
        }
    }

    /**
     * @param  Collection<int, PaymentEvent>  $events
     */
    private function fetch(Payment $payment, Collection $events): ?ProviderPayment
    {
        $providerPaymentId = $payment->provider_payment_id ?? $events->whereNotNull('provider_payment_id')->last()?->provider_payment_id;

        if ($providerPaymentId === null) {
            $this->events->markProcessed($this->idsOf($events), PaymentEventResult::Ignored, 'no_provider_payment');

            return null;
        }

        try {
            return $this->gateway->fetchPayment($payment->mode, $providerPaymentId);
        } catch (ProviderCallFailed $failure) {
            if ($failure->isTransient()) {
                throw $failure;
            }

            $this->events->markProcessed($this->idsOf($events), PaymentEventResult::Rejected, $failure->kind->value);

            return null;
        }
    }

    /**
     * @return Collection<int, PaymentEvent>
     */
    private function unprocessedEventsOf(Payment $payment): Collection
    {
        return PaymentEvent::query()
            ->where('payment_id', $payment->id)
            ->whereNull('processed_at')
            ->orderBy('id')
            ->get(['id', 'payment_id', 'mode', 'provider_payment_id']);
    }

    /**
     * @param  Collection<int, PaymentEvent>  $events
     * @return list<int>
     */
    private function idsOf(Collection $events): array
    {
        return array_values($events->map(static fn (PaymentEvent $event): int => $event->id)->all());
    }
}
