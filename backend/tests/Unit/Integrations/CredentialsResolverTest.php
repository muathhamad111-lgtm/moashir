<?php

declare(strict_types=1);

use App\Enums\Integrations\CredentialSource;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Settings\SettingKey;
use App\Services\Integrations\IntegrationConfigVersions;
use App\Services\Integrations\IntegrationCredentialsResolver;
use Illuminate\Support\Facades\Cache;

function enableTaqnyatBootstrap(): void
{
    config()->set('moashir.integrations.env_fallback', true);
    config()->set('moashir.integrations.environment.sms.provider', 'taqnyat');
    config()->set('moashir.integrations.environment.sms.taqnyat', ['bearer_token' => FAKE_TAQNYAT_TOKEN, 'sender_name' => 'MoashirEnv']);
}

it('resolves the active settings row first', function (): void {
    enableTaqnyatBootstrap();
    configureTaqnyat(['is_active' => true]);

    $credentials = app(IntegrationCredentialsResolver::class)->resolve(IntegrationKind::Sms);

    expect($credentials->source)->toBe(CredentialSource::Settings)
        ->and($credentials->string('sender_name'))->toBe('Moashir')
        ->and($credentials->secret('bearer_token')->reveal())->toBe(FAKE_TAQNYAT_TOKEN);
});

it('uses the environment bootstrap only while no row exists for the kind', function (): void {
    enableTaqnyatBootstrap();
    $resolver = app(IntegrationCredentialsResolver::class);

    expect($resolver->resolve(IntegrationKind::Sms)->source)->toBe(CredentialSource::Environment)
        ->and($resolver->resolve(IntegrationKind::Sms)->string('sender_name'))->toBe('MoashirEnv');

    configureMsegat();

    expect($resolver->resolve(IntegrationKind::Sms)->source)->toBe(CredentialSource::None)
        ->and($resolver->forProvider(IntegrationProvider::Taqnyat)->isConfigured())->toBeFalse();
});

it('never mixes fields of an incomplete row with the environment', function (): void {
    enableTaqnyatBootstrap();
    storeCredential(IntegrationProvider::Taqnyat, null, ['sender_name' => 'Moashir'], [], ['is_active' => true]);

    expect(app(IntegrationCredentialsResolver::class)->resolve(IntegrationKind::Sms)->isConfigured())->toBeFalse();
});

it('picks the Moyasar key pair of the requested or selected mode', function (): void {
    configureMoyasar(IntegrationMode::Test);
    configureMoyasar(IntegrationMode::Live);
    setIntegrationSetting(SettingKey::PaymentMode, 'live');
    $resolver = app(IntegrationCredentialsResolver::class);

    expect($resolver->forProvider(IntegrationProvider::Moyasar)->secret('secret_key')->reveal())->toBe(sprintf(FAKE_MOYASAR_SECRET, 'live'))
        ->and($resolver->forProvider(IntegrationProvider::Moyasar, IntegrationMode::Test)->secret('secret_key')->reveal())->toBe(sprintf(FAKE_MOYASAR_SECRET, 'test'));
});

it('caches only the configuration version of a kind', function (): void {
    configureTaqnyat();
    $versions = app(IntegrationConfigVersions::class);
    $first = $versions->current(IntegrationKind::Sms);

    $cached = Cache::get('integrations:config_version:sms');
    storeCredential(IntegrationProvider::Taqnyat, null, ['sender_name' => 'MoashirNew'], []);

    expect($cached)->toBe($first)
        ->and(is_int($cached))->toBeTrue()
        ->and($versions->current(IntegrationKind::Sms))->toBe($first);

    $versions->forget(IntegrationKind::Sms);

    expect($versions->current(IntegrationKind::Sms))->toBeGreaterThan($first);
});
