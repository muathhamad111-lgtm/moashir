<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Integrations\IntegrationCredential;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

const PAYMENT_SETTINGS = '/api/v1/settings/payment';

/**
 * @return array<string, mixed>
 */
function testKeyPair(): array
{
    return ['test' => [
        'publishable_key' => sprintf(FAKE_MOYASAR_PUBLISHABLE, 'test'),
        'secret_key' => sprintf(FAKE_MOYASAR_SECRET, 'test'),
    ]];
}

function fakeMoyasarProbe(int $webhookStatus = 200, string $webhookUrl = 'http://localhost/api/v1/webhooks/moyasar'): void
{
    fakeProviderRoutes([
        'GET api.moyasar.com/v1/webhooks' => $webhookStatus === 200
            ? Http::response(['webhooks' => [['id' => 'f7a1c2d3-0000-4000-8000-000000000001', 'http_method' => 'post', 'url' => $webhookUrl, 'events' => []]]])
            : Http::response(['type' => 'authentication_error', 'message' => 'Invalid authorization credentials', 'errors' => null], $webhookStatus),
        'POST api.moyasar.com/v1/payment_intents' => Http::response(['id' => '8f4d1c3e-0000-4000-9000-000000000001', 'status' => 'created', 'client_secret' => 'pi_secret_fake', 'amount' => 100, 'currency' => 'SAR'], 201),
        'POST api.moyasar.com/v1/payment_intents/*/cancel' => Http::response(['id' => '8f4d1c3e-0000-4000-9000-000000000001', 'status' => 'canceled']),
    ]);
}

it('shows the payment settings with the values to copy and no secret', function (): void {
    configureMoyasar();
    signIn(systemAdmin());

    $response = $this->getJson(PAYMENT_SETTINGS)
        ->assertOk()
        ->assertJsonPath('data.provider', 'moyasar')
        ->assertJsonPath('data.mode', 'test')
        ->assertJsonPath('data.active', false)
        ->assertJsonPath('data.source', 'settings')
        ->assertJsonPath('data.test.configured', true)
        ->assertJsonPath('data.test.publishable_key', sprintf(FAKE_MOYASAR_PUBLISHABLE, 'test'))
        ->assertJsonPath('data.test.secret_key.set', true)
        ->assertJsonPath('data.test.secret_key.last4', '0001')
        ->assertJsonPath('data.test.webhook_secret.set', true)
        ->assertJsonPath('data.test.webhook_secret.last4', null)
        ->assertJsonPath('data.live.configured', false)
        ->assertJsonPath('data.live.secret_key', ['set' => false, 'last4' => null, 'updated_at' => null, 'updated_by' => null])
        ->assertJsonPath('data.methods', ['creditcard'])
        ->assertJsonPath('data.supported_networks', ['mada', 'visa', 'mastercard'])
        ->assertJsonPath('data.apple_pay_label', ['ar' => 'منصة مؤشر', 'en' => 'Moashir Platform'])
        ->assertJsonPath('data.linked_paths', ['accredited', 'bag', 'self', 'award'])
        ->assertJsonPath('data.values_to_copy.webhook_url', 'http://localhost/api/v1/webhooks/moyasar')
        ->assertJsonPath('data.values_to_copy.webhook_method', 'POST')
        ->assertJsonPath('data.values_to_copy.webhook_events', 'all')
        ->assertJsonPath('data.values_to_copy.apple_pay_association_url', 'https://localhost/.well-known/apple-developer-merchantid-domain-association')
        ->assertJsonPath('data.apple_pay_association_file.set', false)
        ->assertJsonPath('data.is_test_mode_in_production', false);

    assertNoSecretIn((string) $response->getContent());
    expect($response->getContent())->not->toContain('secrets');
});

it('stores the key pair encrypted, returns only its write-only state and audits the replacement', function (): void {
    Notification::fake();
    $admin = systemAdmin();
    signIn($admin);

    $response = $this->putJson(PAYMENT_SETTINGS, testKeyPair())
        ->assertOk()
        ->assertJsonPath('data.test.configured', true)
        ->assertJsonPath('data.test.secret_key.set', true)
        ->assertJsonPath('data.test.secret_key.last4', '0001')
        ->assertJsonPath('data.test.secret_key.updated_by', ['id' => $admin->id, 'name' => $admin->name]);

    $row = IntegrationCredential::query()->where('provider', 'moyasar')->where('mode', 'test')->firstOrFail();
    $raw = (string) DB::table('integration_credentials')->where('id', $row->id)->value('secrets');

    expect($row->secrets['secret_key'] ?? null)->toBe(sprintf(FAKE_MOYASAR_SECRET, 'test'))
        ->and($raw)->not->toContain('sk_test_')
        ->and(Audit::query()->where('event', AuditEvent::SecretReplaced->value)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::SecretReplaced->value)->first()?->new_values)->toEqual([
            'kind' => 'payment', 'provider' => 'moyasar', 'mode' => 'test', 'field' => 'secret_key', 'action' => 'replaced',
        ]);

    assertNoSecretIn((string) $response->getContent());
    assertNoSecretInAudits();
});

it('raises the secret replaced alert to the Super Admins', function (): void {
    Notification::fake();
    $superAdmin = superAdmin();
    signIn(systemAdmin());

    $this->putJson(PAYMENT_SETTINGS, testKeyPair())->assertOk();

    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, static fn (SecurityAlertNotification $notification): bool => $notification->code() === NotificationCode::IntegrationSecretReplaced);
});

it('keeps a stored secret when the field is omitted or empty and deletes it on an explicit clear', function (): void {
    configureMoyasar();
    signIn(systemAdmin());

    $this->putJson(PAYMENT_SETTINGS, ['test' => ['secret_key' => '', 'webhook_secret' => null]])
        ->assertOk()
        ->assertJsonPath('data.test.secret_key.set', true)
        ->assertJsonPath('data.test.webhook_secret.set', true);

    $this->putJson(PAYMENT_SETTINGS, ['test' => ['webhook_secret' => ['clear' => true]]])
        ->assertOk()
        ->assertJsonPath('data.test.webhook_secret.set', false)
        ->assertJsonPath('data.test.secret_key.set', true);

    expect(Audit::query()->where('event', AuditEvent::SecretReplaced->value)->latest('id')->first()?->new_values['action'] ?? null)->toBe('cleared');
});

it('validates the Moyasar keys against their mode', function (array $payload, string $field): void {
    signIn(systemAdmin());

    $response = $this->putJson(PAYMENT_SETTINGS, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    assertNoSecretIn((string) $response->getContent());
})->with([
    'live secret in the test pair' => [['test' => ['secret_key' => sprintf(FAKE_MOYASAR_SECRET, 'live')]], 'test.secret_key'],
    'test publishable key in the live pair' => [['live' => ['publishable_key' => sprintf(FAKE_MOYASAR_PUBLISHABLE, 'test')]], 'live.publishable_key'],
    'secret with whitespace' => [['test' => ['secret_key' => 'sk_test_fake moashir secret 0001']], 'test.secret_key'],
    'secret too short' => [['test' => ['secret_key' => 'sk_test_1']], 'test.secret_key'],
    'clear with another flag' => [['test' => ['secret_key' => ['clear' => false]]], 'test.secret_key'],
    'short webhook secret' => [['test' => ['webhook_secret' => 'short-secret']], 'test.webhook_secret'],
    'unknown pair field' => [['test' => ['account_id' => 'acc_1']], 'test'],
    'leadership linked path' => [['linked_paths' => ['leadership']], 'linked_paths.0'],
    'unknown method' => [['methods' => ['samsungpay']], 'methods.0'],
    'no card network' => [['supported_networks' => []], 'supported_networks'],
    'unknown mode' => [['mode' => 'sandbox'], 'mode'],
    'long Apple Pay label' => [['apple_pay_label' => ['ar' => str_repeat('م', 65), 'en' => 'Moashir']], 'apple_pay_label.ar'],
]);

it('requires a reason from the Super Admin before a secret is replaced', function (): void {
    signIn(superAdmin());

    $this->putJson(PAYMENT_SETTINGS, testKeyPair())->assertUnprocessable()->assertJsonPath('code', 'reason_required');

    $this->putJson(PAYMENT_SETTINGS, [...testKeyPair(), 'reason' => 'Rotating the Moyasar test key after the sandbox review.'])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::SecretReplaced->value)->first()?->reason)->toBe('Rotating the Moyasar test key after the sandbox review.');
});

it('enables Apple Pay and STC Pay only after their confirmations', function (): void {
    signIn(systemAdmin());

    $this->putJson(PAYMENT_SETTINGS, ['methods' => ['creditcard', 'applepay']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'payment.method_requires_confirmation');

    $this->putJson(PAYMENT_SETTINGS, ['methods' => ['creditcard', 'applepay'], 'confirmations' => ['apple_pay_domain' => true]])
        ->assertOk()
        ->assertJsonPath('data.methods', ['creditcard', 'applepay'])
        ->assertJsonPath('data.confirmations.apple_pay_domain.confirmed', true)
        ->assertJsonPath('data.confirmations.stcpay_activation.confirmed', false);

    $this->putJson(PAYMENT_SETTINGS, ['confirmations' => ['apple_pay_domain' => false]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'payment.confirmation_in_use');
});

it('activates the gateway only after a passing test of the selected mode', function (): void {
    configureMoyasar();
    signIn(systemAdmin());

    $this->putJson(PAYMENT_SETTINGS, ['active' => true])->assertUnprocessable()->assertJsonPath('code', 'integration.test_required');

    IntegrationCredential::query()->where('mode', 'test')->firstOrFail()->forceFill(passedTestState())->save();

    $this->putJson(PAYMENT_SETTINGS, ['active' => true])->assertOk()->assertJsonPath('data.active', true)->assertJsonPath('data.test.active', true);

    expect(Audit::query()->where('event', AuditEvent::IntegrationActivated->value)->count())->toBe(1);
});

it('switches to live only with a live key pair that passed its test', function (): void {
    configureMoyasar(IntegrationMode::Test, passedTestState());
    configureMoyasar(IntegrationMode::Live);
    signIn(systemAdmin());

    $this->putJson(PAYMENT_SETTINGS, ['mode' => 'live'])->assertUnprocessable()->assertJsonPath('code', 'integration.test_required');

    IntegrationCredential::query()->where('mode', 'live')->firstOrFail()->forceFill(passedTestState())->save();

    $this->putJson(PAYMENT_SETTINGS, ['mode' => 'live', 'active' => true])
        ->assertOk()
        ->assertJsonPath('data.mode', 'live')
        ->assertJsonPath('data.live.active', true)
        ->assertJsonPath('data.test.active', false);
});

it('asks for a reason and shows the banner flag when production switches payments to test', function (): void {
    configureMoyasar(IntegrationMode::Live, passedTestState());
    setIntegrationSetting(SettingKey::PaymentMode, 'live');
    app()['env'] = 'production';
    $this->withoutMiddleware(ValidateCsrfToken::class);
    signIn(systemAdmin());

    $this->putJson(PAYMENT_SETTINGS, ['mode' => 'test'])->assertUnprocessable()->assertJsonPath('code', 'reason_required');

    $this->putJson(PAYMENT_SETTINGS, ['mode' => 'test', 'reason' => 'Sandbox verification of Apple Pay before the launch.'])
        ->assertOk()
        ->assertJsonPath('data.mode', 'test')
        ->assertJsonPath('data.is_test_mode_in_production', true);

    $this->getJson('/api/v1/settings/integrations')->assertOk()->assertJsonPath('meta.payment_test_mode_in_production', true);

    expect(Audit::query()->where('event', AuditEvent::IntegrationModeChanged->value)->first()?->reason)->toBe('Sandbox verification of Apple Pay before the launch.');
});

it('tests the connection without charging and records the result', function (): void {
    fakeMoyasarProbe();
    configureMoyasar();
    signIn(systemAdmin());

    $this->postJson(PAYMENT_SETTINGS.'/test-connection', ['mode' => 'test'])
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.provider', 'moyasar')
        ->assertJsonPath('data.mode', 'test')
        ->assertJsonPath('data.checks', [
            ['key' => 'key_prefixes', 'ok' => true, 'detail_code' => null],
            ['key' => 'secret_key_valid', 'ok' => true, 'detail_code' => null],
            ['key' => 'intents_available', 'ok' => true, 'detail_code' => null],
        ])
        ->assertJsonPath('data.info.webhook_registered', true);

    Http::assertSent(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/v1/payment_intents') && $request['amount'] === 100);
    Http::assertSent(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/cancel'));
    Http::assertSent(static fn (ClientRequest $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode(sprintf(FAKE_MOYASAR_SECRET, 'test').':')));

    $row = IntegrationCredential::query()->where('mode', 'test')->firstOrFail();
    expect($row->last_test_ok)->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::PaymentConnectionTested->value)->count())->toBe(1);
});

it('answers 502 with the error kind, records the failure and raises INT-03', function (): void {
    Notification::fake();
    $otherAdmin = systemAdmin();
    fakeMoyasarProbe(401);
    configureMoyasar();
    signIn(systemAdmin());

    $response = $this->postJson(PAYMENT_SETTINGS.'/test-connection')
        ->assertStatus(502)
        ->assertJsonPath('code', 'provider_error')
        ->assertJsonPath('errors.provider_error_kind', ['auth_failed'])
        ->assertJsonPath('meta.result.ok', false)
        ->assertJsonPath('meta.result.checks.1', ['key' => 'secret_key_valid', 'ok' => false, 'detail_code' => 'auth_failed']);

    assertNoSecretIn((string) $response->getContent());
    expect(IntegrationCredential::query()->where('mode', 'test')->firstOrFail()->last_test_error_kind?->value)->toBe('auth_failed');
    Notification::assertSentTo($otherAdmin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::PaymentGatewayTestFailed);
});

it('refuses to test a mode that has no key pair', function (): void {
    signIn(systemAdmin());

    $this->postJson(PAYMENT_SETTINGS.'/test-connection', ['mode' => 'live'])->assertConflict()->assertJsonPath('code', 'integration.not_configured');
});

it('logs outbound calls with metadata only', function (): void {
    Log::spy();
    fakeMoyasarProbe();
    configureMoyasar();
    signIn(systemAdmin());

    $this->postJson(PAYMENT_SETTINGS.'/test-connection')->assertOk();

    Log::shouldHaveReceived('info')->withArgs(static function (string $message, array $context): bool {
        if ($message !== 'integration.outbound_call') {
            return false;
        }

        assertNoSecretIn((string) json_encode($context));

        return array_keys($context) === ['provider', 'operation', 'status', 'latency_ms', 'request_id', 'provider_request_id'];
    })->atLeast()->once();
});

it('limits connection tests to three per minute per user', function (): void {
    fakeMoyasarProbe();
    configureMoyasar();
    signIn(systemAdmin());

    foreach (range(1, 3) as $attempt) {
        $this->postJson(PAYMENT_SETTINGS.'/test-connection')->assertOk();
    }

    $this->postJson(PAYMENT_SETTINGS.'/test-connection')->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});

it('registers the webhook with a generated secret and replaces an older registration', function (): void {
    config()->set('app.url', 'https://moashir.example.sa');
    configureMoyasar(IntegrationMode::Test, passedTestState());
    fakeProviderRoutes([
        'GET api.moyasar.com/v1/webhooks' => Http::response(['webhooks' => [['id' => 'old-hook', 'http_method' => 'post', 'url' => 'https://moashir.example.sa/api/v1/webhooks/moyasar', 'events' => []]]]),
        'DELETE api.moyasar.com/v1/webhooks/old-hook' => Http::response(['message' => 'Webhook was deleted successfully']),
        'POST api.moyasar.com/v1/webhooks' => static fn (ClientRequest $request) => Http::response(['id' => 'new-hook', 'http_method' => 'post', 'url' => $request['url'], 'events' => []], 201),
    ]);
    signIn(systemAdmin());

    $response = $this->postJson(PAYMENT_SETTINGS.'/webhook/register', ['mode' => 'test'])
        ->assertCreated()
        ->assertJsonPath('data', ['mode' => 'test', 'webhook_id' => 'new-hook', 'url' => 'https://moashir.example.sa/api/v1/webhooks/moyasar']);

    $row = IntegrationCredential::query()->where('mode', 'test')->firstOrFail();
    $secret = $row->secrets['webhook_secret'] ?? '';

    Http::assertSent(static fn (ClientRequest $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/v1/webhooks')
        && $request['shared_secret'] === $secret && $request['http_method'] === 'post');
    Http::assertSent(static fn (ClientRequest $request): bool => $request->method() === 'DELETE');

    expect(strlen($secret))->toBe(64)
        ->and($secret)->not->toBe(FAKE_WEBHOOK_SECRET)
        ->and($row->providerRef('webhook_id'))->toBe('new-hook')
        ->and($row->last_test_ok)->toBeTrue()
        ->and($response->getContent())->not->toContain($secret)
        ->and(Audit::query()->where('event', AuditEvent::PaymentWebhookRegistered->value)->count())->toBe(1);

    $this->getJson(PAYMENT_SETTINGS)->assertJsonPath('data.test.webhook.id', 'new-hook')->assertJsonPath('data.test.webhook_secret.set', true);
});

it('refuses automatic webhook registration without HTTPS', function (): void {
    configureMoyasar();
    signIn(systemAdmin());

    $this->postJson(PAYMENT_SETTINGS.'/webhook/register', ['mode' => 'test'])->assertConflict()->assertJsonPath('code', 'payment.webhook_requires_https');
});

it('keeps every payment endpoint for the system administrator and the Super Admin', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(PAYMENT_SETTINGS)->assertForbidden();
    $this->putJson(PAYMENT_SETTINGS, ['methods' => ['creditcard']])->assertForbidden();
    $this->postJson(PAYMENT_SETTINGS.'/test-connection')->assertForbidden();
    $this->postJson(PAYMENT_SETTINGS.'/webhook/register', ['mode' => 'test'])->assertForbidden();
    $this->postJson(PAYMENT_SETTINGS.'/apple-pay/association-file')->assertForbidden();
    $this->deleteJson(PAYMENT_SETTINGS.'/apple-pay/association-file')->assertForbidden();
    $this->postJson(PAYMENT_SETTINGS.'/apple-pay/association-file/check')->assertForbidden();
})->with(nonSystemAdminRoles());

it('lets the Super Admin read the payment settings and denies entities and guests', function (): void {
    $this->getJson(PAYMENT_SETTINGS)->assertUnauthorized();

    signIn(entityUser());
    $this->getJson(PAYMENT_SETTINGS)->assertForbidden();

    switchTo(superAdmin());
    $this->getJson(PAYMENT_SETTINGS)->assertOk();
});
