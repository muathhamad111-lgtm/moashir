<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Integrations\ProviderPayment;
use App\Data\Integrations\VerifiedPaymentEvent;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Models\Identity\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentEventRecorder
{
    public const string PROVIDER = 'moyasar';

    public function recordCheckout(Payment $payment, string $intentId): void
    {
        $this->record($payment, PaymentEventSource::Checkout, 'intent_created', ['intent_id' => $intentId], PaymentEventResult::Applied);
    }

    public function recordProviderFetch(Payment $payment, ProviderPayment $provider, PaymentEventSource $source, PaymentEventResult $result): void
    {
        $this->record($payment, $source, 'payment_'.$provider->status, $this->summaryOf($provider), $result, $provider->id);
    }

    public function recordExpiry(Payment $payment, string $type): void
    {
        $this->record($payment, PaymentEventSource::Expiry, $type, ['status' => $payment->status->value], PaymentEventResult::Applied);
    }

    /**
     * @param  array<string, scalar|null>  $payload
     */
    public function recordAdmin(Payment $payment, string $type, User $actor, array $payload): void
    {
        $this->record($payment, PaymentEventSource::Admin, $type, $payload, PaymentEventResult::Applied, $payment->provider_payment_id, $actor->id);
    }

    public function recordWebhook(VerifiedPaymentEvent $event, ?Payment $payment, ?string $ipAddress): ?PaymentEvent
    {
        try {
            return DB::transaction(static fn (): PaymentEvent => PaymentEvent::query()->create([
                'payment_id' => $payment?->id,
                'provider' => self::PROVIDER,
                'provider_event_id' => mb_substr($event->eventId, 0, 120),
                'source' => PaymentEventSource::Webhook,
                'event_type' => mb_substr($event->type, 0, 40),
                'mode' => $event->mode,
                'signature_valid' => true,
                'provider_payment_id' => $event->providerPaymentId === null ? null : mb_substr($event->providerPaymentId, 0, 80),
                'payload' => self::objectOf($event->redactedPayload),
                'ip_address' => $ipAddress,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * @param  list<int>  $eventIds
     */
    public function markProcessed(array $eventIds, PaymentEventResult $result, ?string $errorCode = null): void
    {
        if ($eventIds === []) {
            return;
        }

        PaymentEvent::query()->whereIn('id', $eventIds)->whereNull('processed_at')->update([
            'processed_at' => Date::now(),
            'processing_result' => $result->value,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(
        Payment $payment,
        PaymentEventSource $source,
        string $type,
        array $payload,
        PaymentEventResult $result,
        ?string $providerPaymentId = null,
        ?int $actorId = null,
    ): void {
        PaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'provider' => self::PROVIDER,
            'provider_event_id' => $source->value.':'.Str::uuid()->toString(),
            'source' => $source,
            'event_type' => mb_substr($type, 0, 40),
            'mode' => $payment->mode,
            'provider_payment_id' => $providerPaymentId,
            'payload' => $payload,
            'actor_user_id' => $actorId,
            'processed_at' => Date::now(),
            'processing_result' => $result,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryOf(ProviderPayment $provider): array
    {
        return [
            'id' => $provider->id,
            'status' => $provider->status,
            'amount' => $provider->amount->amount,
            'currency' => $provider->amount->currency,
            'source' => ['type' => $provider->sourceType, 'company' => $provider->cardCompany, 'last4' => $provider->cardLast4],
            'response_code' => $provider->responseCode,
            'metadata' => $provider->metadata,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function objectOf(array $payload): array
    {
        $object = [];

        foreach ($payload as $key => $value) {
            $object[(string) $key] = $value;
        }

        return $object;
    }
}
