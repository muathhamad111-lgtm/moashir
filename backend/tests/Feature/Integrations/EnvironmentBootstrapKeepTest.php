<?php

declare(strict_types=1);

use App\Models\Integrations\IntegrationCredential;

beforeEach(function (): void {
    config()->set('moashir.integrations.env_fallback', true);
    signIn(systemAdmin());
});

function assertStillOnEnvironment(string $endpoint): void
{
    test()->getJson($endpoint)->assertOk()->assertJsonPath('data.source', 'environment');

    expect(IntegrationCredential::query()->count())->toBe(0);
}

it('keeps the SMS bootstrap when a save carries only keep values', function (): void {
    config()->set('moashir.integrations.environment.sms', [
        'provider' => 'taqnyat',
        'taqnyat' => ['bearer_token' => FAKE_TAQNYAT_TOKEN, 'sender_name' => 'Moashir'],
        'msegat' => ['username' => null, 'api_key' => null, 'sender_name' => null],
    ]);

    $this->putJson('/api/v1/settings/sms', ['providers' => [
        'taqnyat' => ['bearer_token' => null, 'sender_name' => ''],
        'msegat' => ['username' => null, 'api_key' => '', 'sender_name' => null],
    ]])->assertOk()->assertJsonPath('data.source', 'environment');

    assertStillOnEnvironment('/api/v1/settings/sms');

    $this->putJson('/api/v1/settings/sms', ['providers' => ['taqnyat' => ['sender_name' => 'MoashirSA']]])->assertOk()->assertJsonPath('data.source', 'settings');
    expect(IntegrationCredential::query()->count())->toBe(1);
});

it('keeps the e-mail bootstrap when a save carries only keep values', function (): void {
    config()->set('mail.mailers.smtp', [...config()->array('mail.mailers.smtp'), 'host' => 'smtp.moashir.example.sa', 'port' => 587, 'username' => 'notifications@moashir.example.sa', 'password' => FAKE_SMTP_PASSWORD]);
    config()->set('mail.from', ['address' => 'no-reply@moashir.example.sa', 'name' => 'Moashir']);
    $this->getJson('/api/v1/settings/email')->assertOk()->assertJsonPath('data.source', 'environment');

    $this->putJson('/api/v1/settings/email', ['host' => null, 'username' => '', 'password' => null, 'from_address' => null])
        ->assertOk()
        ->assertJsonPath('data.source', 'environment');

    assertStillOnEnvironment('/api/v1/settings/email');
});

it('keeps the AI bootstrap when a save carries only keep values', function (): void {
    config()->set('moashir.integrations.environment.ai', ['provider' => 'openai', 'api_key' => FAKE_OPENAI_KEY, 'model' => 'gpt-4.1-mini']);

    $this->putJson('/api/v1/settings/ai', ['providers' => [
        'openai' => ['api_key' => null, 'model' => null, 'data_transfer_acknowledged' => false],
        'anthropic' => ['api_key' => '', 'workspace_id' => null],
    ]])->assertOk()->assertJsonPath('data.source', 'environment');

    assertStillOnEnvironment('/api/v1/settings/ai');
});

it('keeps the payment bootstrap when a save carries only keep values', function (): void {
    config()->set('moashir.integrations.environment.payment', [
        'mode' => 'test',
        'publishable_key' => sprintf(FAKE_MOYASAR_PUBLISHABLE, 'test'),
        'secret_key' => sprintf(FAKE_MOYASAR_SECRET, 'test'),
        'webhook_secret' => FAKE_WEBHOOK_SECRET,
    ]);

    $this->putJson('/api/v1/settings/payment', [
        'test' => ['publishable_key' => null, 'secret_key' => '', 'webhook_secret' => null],
        'live' => ['secret_key' => null],
    ])->assertOk()->assertJsonPath('data.source', 'environment');

    assertStillOnEnvironment('/api/v1/settings/payment');
});
