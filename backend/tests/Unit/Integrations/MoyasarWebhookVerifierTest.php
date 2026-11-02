<?php

declare(strict_types=1);

use App\Contracts\Integrations\PaymentWebhookVerifier;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Exceptions\Integrations\WebhookRejected;
use App\Integrations\Moyasar\MoyasarWebhookVerifier;

/**
 * @param  array<string, mixed>  $overrides
 */
function moyasarWebhookPayload(array $overrides = []): string
{
    return (string) json_encode([
        'id' => 'evt-0001',
        'type' => 'payment_paid',
        'created_at' => '2026-11-01T08:00:00Z',
        'secret_token' => FAKE_WEBHOOK_SECRET,
        'account_name' => 'Moashir',
        'live' => false,
        'data' => [
            'id' => '760878ec-0000-4000-8000-000000000001',
            'status' => 'paid',
            'amount' => 575000,
            'ip' => '62.149.100.20',
            'metadata' => ['moashir_payment_id' => '2f9d8a52-0000-4000-8000-000000000014'],
            'source' => ['type' => 'stcpay', 'name' => 'Salman Al-Otaibi', 'mobile' => '0551234567', 'number' => '4201-32XX-XXXX-1111'],
        ],
        ...$overrides,
    ]);
}

function webhookRejectionCode(string $payload): string
{
    try {
        app(PaymentWebhookVerifier::class)->verify($payload);
    } catch (WebhookRejected $rejection) {
        return $rejection->error()->code;
    }

    return 'accepted';
}

it('accepts a payload carrying the secret token of its mode and redacts it before storage', function (): void {
    configureMoyasar();

    $event = app(PaymentWebhookVerifier::class)->verify(moyasarWebhookPayload());

    expect($event->eventId)->toBe('evt-0001')
        ->and($event->type)->toBe('payment_paid')
        ->and($event->mode)->toBe(IntegrationMode::Test)
        ->and($event->providerPaymentId)->toBe('760878ec-0000-4000-8000-000000000001')
        ->and($event->moashirPaymentId)->toBe('2f9d8a52-0000-4000-8000-000000000014')
        ->and($event->redactedPayload)->not->toHaveKey('secret_token')
        ->and($event->redactedPayload['data'])->not->toHaveKey('ip')
        ->and($event->redactedPayload['data']['source'])->toBe(['type' => 'stcpay', 'number' => '1111']);

    assertNoSecretIn((string) json_encode($event->redactedPayload));
});

it('checks the token against the secret of the mode named by the payload', function (): void {
    configureMoyasar(IntegrationMode::Test);
    storeCredential(IntegrationProvider::Moyasar, IntegrationMode::Live, ['publishable_key' => sprintf(FAKE_MOYASAR_PUBLISHABLE, 'live')], [
        'secret_key' => sprintf(FAKE_MOYASAR_SECRET, 'live'),
        'webhook_secret' => 'fake-live-webhook-shared-secret-value-001',
    ]);

    expect(webhookRejectionCode(moyasarWebhookPayload(['live' => true])))->toBe('webhook.unauthenticated')
        ->and(webhookRejectionCode(moyasarWebhookPayload(['live' => true, 'secret_token' => 'fake-live-webhook-shared-secret-value-001'])))->toBe('accepted');
});

it('rejects a missing, wrong or unconfigured secret token', function (array $overrides): void {
    configureMoyasar();

    expect(webhookRejectionCode(moyasarWebhookPayload($overrides)))->toBe('webhook.unauthenticated');
})->with([
    'wrong token' => [['secret_token' => 'fake-webhook-shared-secret-value-0000000002']],
    'missing token' => [['secret_token' => null]],
    'empty token' => [['secret_token' => '']],
    'live mode without keys' => [['live' => true]],
]);

it('rejects malformed or oversized payloads before any lookup', function (string $payload): void {
    expect(webhookRejectionCode($payload))->toBe('webhook.malformed');
})->with([
    'not json' => ['payment_paid'],
    'no event id' => [(string) json_encode(['type' => 'payment_paid', 'live' => false])],
    'live is not a boolean' => [(string) json_encode(['id' => 'e', 'type' => 'payment_paid', 'live' => 'false'])],
    'empty' => [''],
    'too large' => [str_repeat('a', MoyasarWebhookVerifier::MAX_PAYLOAD_BYTES + 1)],
]);
