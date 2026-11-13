<?php

declare(strict_types=1);

use App\Contracts\Integrations\HostResolver;
use App\Contracts\SmsGateway;
use App\Data\Integrations\CredentialChanges;
use App\Data\Integrations\SmsDriverSelection;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Integrations\CredentialWriter;
use App\Services\Integrations\IntegrationRows;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\SecretValue;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakeHostResolver;

const FAKE_MOYASAR_PUBLISHABLE = 'pk_%s_fakeMoashirPublishable0001';
const FAKE_MOYASAR_SECRET = 'sk_%s_fakeMoashirSecretKey000001';
const FAKE_WEBHOOK_SECRET = 'fake-webhook-shared-secret-value-0000000001';
const FAKE_TAQNYAT_TOKEN = 'fake-taqnyat-bearer-token-000000000001';
const FAKE_MSEGAT_KEY = 'fake-msegat-api-key-000000000001';
const FAKE_OPENAI_KEY = 'sk-fake-openai-key-0000000000000001';
const FAKE_ANTHROPIC_KEY = 'sk-ant-fake-anthropic-key-00000000001';
const FAKE_SMTP_PASSWORD = 'fake-smtp-password-0001';

/**
 * @param  array<string, mixed>  $public
 * @param  array<string, string>  $secrets
 * @param  array<string, mixed>  $state
 */
function storeCredential(IntegrationProvider $provider, ?IntegrationMode $mode, array $public, array $secrets, array $state = []): IntegrationCredential
{
    $row = app(IntegrationRows::class)->findOrNew($provider, $mode);
    $values = array_map(static fn (string $secret): SecretValue => new SecretValue($secret), $secrets);
    app(CredentialWriter::class)->apply($row, new CredentialChanges($public, $values), null);

    if ($state !== []) {
        $row->forceFill($state)->save();
    }

    return $row->refresh();
}

/**
 * @return array<string, mixed>
 */
function passedTestState(): array
{
    return ['last_test_at' => Date::now(), 'last_test_ok' => true, 'last_test_latency_ms' => 120];
}

/**
 * @param  array<string, mixed>  $state
 */
function configureMoyasar(IntegrationMode $mode = IntegrationMode::Test, array $state = []): IntegrationCredential
{
    return storeCredential(IntegrationProvider::Moyasar, $mode, [
        'publishable_key' => sprintf(FAKE_MOYASAR_PUBLISHABLE, $mode->value),
    ], [
        'secret_key' => sprintf(FAKE_MOYASAR_SECRET, $mode->value),
        'webhook_secret' => FAKE_WEBHOOK_SECRET,
    ], $state);
}

/**
 * @param  array<string, mixed>  $state
 */
function configureTaqnyat(array $state = []): IntegrationCredential
{
    return storeCredential(IntegrationProvider::Taqnyat, null, ['sender_name' => 'Moashir'], ['bearer_token' => FAKE_TAQNYAT_TOKEN], $state);
}

/**
 * @param  array<string, mixed>  $public
 * @param  array<string, mixed>  $state
 */
function configureMsegat(array $public = [], array $state = []): IntegrationCredential
{
    return storeCredential(IntegrationProvider::Msegat, null, ['username' => 'moashir-account', 'sender_name' => 'Moashir', ...$public], ['api_key' => FAKE_MSEGAT_KEY], $state);
}

/**
 * @param  array<string, mixed>  $state
 */
function configureSmtp(array $state = []): IntegrationCredential
{
    return storeCredential(IntegrationProvider::Smtp, null, [
        'host' => 'smtp.moashir.example.sa',
        'port' => 587,
        'encryption' => 'starttls',
        'username' => 'notifications@moashir.example.sa',
        'from_address' => 'no-reply@moashir.example.sa',
        'from_name' => ['ar' => 'منصة مؤشر', 'en' => 'Moashir Platform'],
    ], ['password' => FAKE_SMTP_PASSWORD], $state);
}

/**
 * @param  array<string, mixed>  $public
 * @param  array<string, mixed>  $state
 */
function configureOpenAi(array $public = [], array $state = []): IntegrationCredential
{
    return storeCredential(IntegrationProvider::OpenAi, null, ['model' => 'gpt-4.1-mini', ...$public], ['api_key' => FAKE_OPENAI_KEY], $state);
}

/**
 * @param  array<string, mixed>  $public
 * @param  array<string, mixed>  $state
 */
function configureAnthropic(array $public = [], array $state = []): IntegrationCredential
{
    return storeCredential(IntegrationProvider::Anthropic, null, ['model' => 'claude-sonnet-4-6', ...$public], ['api_key' => FAKE_ANTHROPIC_KEY], $state);
}

function useSettingsSmsGateway(): void
{
    config()->set('moashir.sms.driver', 'settings');
    app()->forgetInstance(SmsDriverSelection::class);
    app()->forgetInstance(SmsGateway::class);
}

function setIntegrationSetting(SettingKey $key, mixed $value): void
{
    app(SettingsStore::class)->put($key, $value, AuditEvent::PaymentSettingsUpdated, null);
}

/**
 * @param  array<string, mixed>  $routes
 */
function fakeProviderRoutes(array $routes): void
{
    Http::fake(static function (ClientRequest $request) use ($routes) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_HOST).parse_url($request->url(), PHP_URL_PATH);

        foreach ($routes as $pattern => $response) {
            if (Str::is($pattern, $key)) {
                return $response instanceof Closure || $response instanceof ResponseSequence ? $response($request) : $response;
            }
        }

        return null;
    });
}

/**
 * @param  array<string, list<string>>  $addresses
 * @param  array<string, list<string>>  $textRecords
 */
function resolveHosts(array $addresses, array $textRecords = []): void
{
    app()->instance(HostResolver::class, new FakeHostResolver($addresses, $textRecords));
}

/**
 * @return list<string>
 */
function integrationSecrets(): array
{
    return [
        sprintf(FAKE_MOYASAR_SECRET, 'test'),
        sprintf(FAKE_MOYASAR_SECRET, 'live'),
        FAKE_WEBHOOK_SECRET,
        FAKE_TAQNYAT_TOKEN,
        FAKE_MSEGAT_KEY,
        FAKE_OPENAI_KEY,
        FAKE_ANTHROPIC_KEY,
        FAKE_SMTP_PASSWORD,
    ];
}

function assertNoSecretIn(string $haystack): void
{
    foreach (integrationSecrets() as $secret) {
        expect($haystack)->not->toContain($secret);
    }
}

function assertNoSecretInAudits(): void
{
    assertNoSecretIn((string) json_encode(Audit::query()->get(['old_values', 'new_values', 'reason'])->toArray()));
}

/**
 * @return list<RoleKey>
 */
function nonSystemAdminRoles(): array
{
    return array_values(array_filter(
        RoleKey::cases(),
        static fn (RoleKey $role): bool => $role->isStaff() && $role !== RoleKey::SystemAdmin,
    ));
}
