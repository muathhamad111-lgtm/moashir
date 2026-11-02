<?php

declare(strict_types=1);

use App\Contracts\Integrations\PaymentGateway;
use App\Data\Integrations\PaymentCheckoutRequest;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Integrations\Moyasar\MoyasarAmount;
use App\Support\ValueObjects\Money;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * @return array<string, mixed>
 */
function moyasarPaymentFixture(): array
{
    return [
        'id' => '760878ec-0000-4000-8000-000000000001',
        'status' => 'paid',
        'amount' => 575000,
        'fee' => 0,
        'currency' => 'SAR',
        'refunded' => 0,
        'description' => 'Moashir ACC-2026-000014',
        'ip' => '62.149.100.20',
        'metadata' => ['moashir_payment_id' => '2f9d8a52-0000-4000-8000-000000000014', 'purpose' => 'accredited', 'attempt' => 2],
        'source' => [
            'type' => 'creditcard',
            'company' => 'mada',
            'name' => 'Salman Al-Otaibi',
            'number' => '4201-32XX-XXXX-1111',
            'message' => '<b>APPROVED</b>',
            'response_code' => '00',
        ],
        'created_at' => '2026-11-01T08:00:00.000Z',
    ];
}

it('converts decimal riyals to halalas only inside the adapter', function (string $amount, int $halalas): void {
    expect(MoyasarAmount::toHalalas(new Money($amount)))->toBe($halalas)
        ->and(MoyasarAmount::fromHalalas($halalas, 'SAR')->amount)->toBe($amount);
})->with([['5750.00', 575000], ['1.00', 100], ['0.05', 5], ['1234.56', 123456]]);

it('creates a payment intent with the amount locked in halalas and the secret key of the mode', function (): void {
    configureMoyasar(IntegrationMode::Live);
    fakeProviderRoutes(['POST api.moyasar.com/v1/payment_intents' => Http::response([
        'id' => '8f4d1c3e-0000-4000-9000-000000000001', 'amount' => 575000, 'currency' => 'SAR', 'status' => 'created',
        'client_secret' => 'pi_secret_fakeMoashir', 'expires_at' => '2026-11-01T09:00:00.000Z',
    ], 201)]);

    $session = app(PaymentGateway::class)->createCheckout(new PaymentCheckoutRequest(IntegrationMode::Live, new Money('5750.00'), 'Moashir ACC-2026-000014', 'https://moashir.example.sa/payments/abc/return'));

    expect($session->intentId)->toBe('8f4d1c3e-0000-4000-9000-000000000001')
        ->and($session->amount->amount)->toBe('5750.00')
        ->and($session->clientSecret->reveal())->toBe('pi_secret_fakeMoashir')
        ->and($session->expiresAt?->toIso8601String())->toBe('2026-11-01T09:00:00+00:00');

    Http::assertSent(static fn (ClientRequest $request): bool => $request['amount'] === 575000 && $request['currency'] === 'SAR'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode(sprintf(FAKE_MOYASAR_SECRET, 'live').':')));
});

it('fetches a payment and keeps only the safe fields', function (): void {
    configureMoyasar();
    fakeProviderRoutes(['GET api.moyasar.com/v1/payments/*' => Http::response(moyasarPaymentFixture())]);

    $payment = app(PaymentGateway::class)->fetchPayment(IntegrationMode::Test, '760878ec-0000-4000-8000-000000000001');

    expect($payment->status)->toBe('paid')
        ->and($payment->amount->amount)->toBe('5750.00')
        ->and($payment->sourceType)->toBe('creditcard')
        ->and($payment->cardCompany)->toBe('mada')
        ->and($payment->cardLast4)->toBe('1111')
        ->and($payment->message)->toBe('APPROVED')
        ->and($payment->metadataValue('moashir_payment_id'))->toBe('2f9d8a52-0000-4000-8000-000000000014')
        ->and($payment->metadata['attempt'])->toBe('2')
        ->and(json_encode($payment))->not->toContain('Salman')->not->toContain('62.149.100.20');
});

it('retries a payment fetch on transient errors with backoff', function (): void {
    Sleep::fake();
    configureMoyasar();
    fakeProviderRoutes(['GET api.moyasar.com/v1/payments/*' => Http::sequence()
        ->push(['type' => 'api_error', 'message' => 'Server error', 'errors' => null], 503)
        ->push(['type' => 'rate_limit_error', 'message' => 'Too many', 'errors' => null], 429)
        ->push(moyasarPaymentFixture())]);

    expect(app(PaymentGateway::class)->fetchPayment(IntegrationMode::Test, 'p1')->status)->toBe('paid');

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('maps Moyasar errors to provider error kinds', function (int $status, array $body, ProviderErrorKind $kind): void {
    configureMoyasar();
    fakeProviderRoutes(['POST api.moyasar.com/v1/payment_intents/*/cancel' => Http::response($body, $status)]);

    try {
        app(PaymentGateway::class)->cancelCheckout(IntegrationMode::Test, 'intent-1');
        $this->fail('The cancellation should have failed.');
    } catch (ProviderCallFailed $failure) {
        expect($failure->kind)->toBe($kind)
            ->and($failure->getMessage())->not->toContain('sk_test_');
    }
})->with([
    'not cancelable' => [400, ['type' => 'intent_not_cancelable', 'message' => 'Intent cannot be canceled', 'errors' => null], ProviderErrorKind::Rejected],
    'bad key' => [401, ['type' => 'authentication_error', 'message' => 'Invalid authorization credentials', 'errors' => null], ProviderErrorKind::AuthFailed],
    'forbidden' => [403, ['type' => 'invalid_request_error', 'message' => 'Forbidden', 'errors' => null], ProviderErrorKind::Misconfigured],
    'live account not activated' => [405, ['type' => 'account_inactive_error', 'message' => 'Not activated', 'errors' => null], ProviderErrorKind::AccountInactive],
    'rate limited' => [429, ['type' => 'rate_limit_error', 'message' => 'Too many requests', 'errors' => null], ProviderErrorKind::RateLimited],
    'maintenance' => [503, ['type' => 'api_error', 'message' => 'Maintenance', 'errors' => null], ProviderErrorKind::Unavailable],
]);

it('keeps the intent cancellation code for the payments domain', function (): void {
    configureMoyasar();
    fakeProviderRoutes(['POST api.moyasar.com/v1/payment_intents/*/cancel' => Http::response(['type' => 'intent_not_cancelable', 'message' => 'x', 'errors' => null], 400)]);

    expect(fn () => app(PaymentGateway::class)->cancelCheckout(IntegrationMode::Test, 'intent-1'))
        ->toThrow(static fn (ProviderCallFailed $failure): bool => $failure->providerCode === 'intent_not_cancelable');
});

it('maps a network failure without sending anything twice for a cancellation', function (): void {
    configureMoyasar();
    Http::fake(['api.moyasar.com/*' => Http::failedConnection()]);

    expect(fn () => app(PaymentGateway::class)->cancelCheckout(IntegrationMode::Test, 'intent-1'))
        ->toThrow(static fn (ProviderCallFailed $failure): bool => $failure->kind === ProviderErrorKind::Unavailable);
});

it('never calls Moyasar for a mode without keys', function (): void {
    expect(fn () => app(PaymentGateway::class)->fetchPayment(IntegrationMode::Live, 'p1'))
        ->toThrow(static fn (ProviderCallFailed $failure): bool => $failure->kind === ProviderErrorKind::Misconfigured);

    Http::assertNothingSent();
});
