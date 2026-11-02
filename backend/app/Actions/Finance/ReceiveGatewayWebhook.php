<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Integrations\PaymentWebhookVerifier;
use App\Data\Integrations\VerifiedPaymentEvent;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Integrations\IntegrationMode;
use App\Exceptions\Integrations\WebhookRejected;
use App\Jobs\Finance\ProcessGatewayWebhookJob;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Services\Finance\PaymentAlerts;
use App\Services\Finance\PaymentEventRecorder;
use App\Services\Finance\WebhookPaymentMatcher;
use Illuminate\Support\Facades\Log;

final readonly class ReceiveGatewayWebhook
{
    public function __construct(
        private PaymentWebhookVerifier $verifier,
        private WebhookPaymentMatcher $matcher,
        private PaymentEventRecorder $events,
        private PaymentAlerts $alerts,
    ) {}

    public function handle(string $payload, ?string $ipAddress): bool
    {
        $event = $this->verified($payload, $ipAddress);
        $payment = $this->matcher->match($event);
        $stored = $this->events->recordWebhook($event, $payment, $ipAddress);

        if (! $stored instanceof PaymentEvent) {
            return false;
        }

        if (! $payment instanceof Payment) {
            $this->events->markProcessed([$stored->id], PaymentEventResult::Unmatched);

            if ($event->mode === IntegrationMode::Live) {
                $this->alerts->serviceIssue('unmatched_live_webhook');
            }

            return true;
        }

        ProcessGatewayWebhookJob::dispatch($payment->id);

        return true;
    }

    private function verified(string $payload, ?string $ipAddress): VerifiedPaymentEvent
    {
        try {
            return $this->verifier->verify($payload);
        } catch (WebhookRejected $rejection) {
            Log::warning('payment.webhook_rejected', ['code' => $rejection->error()->code, 'ip' => $ipAddress, 'bytes' => strlen($payload)]);

            throw $rejection;
        }
    }
}
