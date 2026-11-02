<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Integrations\PaymentGateway;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Jobs\Finance\ProcessGatewayWebhookJob;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\PaymentOutcomeApplier;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;

final readonly class ReconcilePendingPayments
{
    private const int CHUNK = 100;

    public function __construct(
        private PaymentGateway $gateway,
        private PaymentOutcomeApplier $applier,
        private FinanceSettings $settings,
    ) {}

    public function handle(): int
    {
        return $this->reconcileStalePending() + $this->redispatchStuckWebhooks();
    }

    private function reconcileStalePending(): int
    {
        $handled = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('expires_at', '>', Date::now())
            ->where('updated_at', '<=', Date::now()->subMinutes($this->settings->reconcileAfterMinutes()))
            ->chunkById(self::CHUNK, function ($payments) use (&$handled): void {
                foreach ($payments as $payment) {
                    $handled += $this->reconcile($payment) ? 1 : 0;
                }
            });

        return $handled;
    }

    private function reconcile(Payment $payment): bool
    {
        if ($payment->provider_payment_id === null) {
            return false;
        }

        try {
            $provider = $this->gateway->fetchPayment($payment->mode, $payment->provider_payment_id);
        } catch (ProviderCallFailed $failure) {
            if (! $failure->isTransient()) {
                report($failure);
            }

            return false;
        }

        $this->applier->apply($payment, $provider, PaymentEventSource::Reconciliation);

        return true;
    }

    private function redispatchStuckWebhooks(): int
    {
        $paymentIds = PaymentEvent::query()
            ->where('source', PaymentEventSource::Webhook)
            ->whereNull('processed_at')
            ->whereNotNull('payment_id')
            ->where('created_at', '<=', Date::now()->subMinutes($this->settings->webhookRedispatchAfterMinutes()))
            ->distinct()
            ->pluck('payment_id');

        foreach ($paymentIds as $paymentId) {
            ProcessGatewayWebhookJob::dispatch(ScalarValue::int($paymentId));
        }

        return $paymentIds->count();
    }
}
