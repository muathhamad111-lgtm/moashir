<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Contracts\Integrations\PaymentWebhookVerifier;
use App\Data\Integrations\VerifiedPaymentEvent;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Exceptions\Integrations\WebhookRejected;
use App\Services\Integrations\IntegrationCredentialsResolver;
use App\Support\ScalarValue;
use JsonException;

final readonly class MoyasarWebhookVerifier implements PaymentWebhookVerifier
{
    public const int MAX_PAYLOAD_BYTES = 262144;

    public function __construct(private IntegrationCredentialsResolver $resolver) {}

    public function verify(string $payload): VerifiedPaymentEvent
    {
        $event = $this->decode($payload);
        $mode = IntegrationMode::fromLiveFlag($event['live'] === true);

        $this->authenticate($mode, $event['secret_token'] ?? null);

        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        return new VerifiedPaymentEvent(
            ScalarValue::string($event['id']),
            ScalarValue::string($event['type']),
            $mode,
            is_string($data['id'] ?? null) ? $data['id'] : null,
            is_string($metadata['moashir_payment_id'] ?? null) ? $metadata['moashir_payment_id'] : null,
            MoyasarPayloadRedactor::redact($event),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $payload): array
    {
        if ($payload === '' || strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            throw WebhookRejected::malformed();
        }

        try {
            $event = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw WebhookRejected::malformed();
        }

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_bool($event['live'] ?? null)) {
            throw WebhookRejected::malformed();
        }

        $decoded = [];

        foreach ($event as $key => $value) {
            $decoded[(string) $key] = $value;
        }

        return $decoded;
    }

    private function authenticate(IntegrationMode $mode, mixed $token): void
    {
        $credentials = $this->resolver->forProvider(IntegrationProvider::Moyasar, $mode);

        if (! is_string($token) || $token === '' || ! $credentials->isConfigured() || ! $credentials->hasSecret('webhook_secret')) {
            throw WebhookRejected::unauthenticated();
        }

        if (! hash_equals($credentials->secret('webhook_secret')->reveal(), $token)) {
            throw WebhookRejected::unauthenticated();
        }
    }
}
