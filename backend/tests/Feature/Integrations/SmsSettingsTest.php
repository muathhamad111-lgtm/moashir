<?php

declare(strict_types=1);

use App\Contracts\SmsGateway;
use App\Data\Integrations\SmsDriverSelection;
use App\Data\Integrations\SmsMessage;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmsPurpose;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Models\Integrations\IntegrationCredential;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Integrations\SettingsSmsGateway;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

const SMS_SETTINGS = '/api/v1/settings/sms';

const TEST_NUMBER = '+966551234567';

function fakeTaqnyatAccount(int $balanceStatus = 201, string $senderStatus = 'active'): void
{
    fakeProviderRoutes([
        'GET api.taqnyat.sa/system/status' => Http::response(['statusCode' => 200, 'status' => ['service' => 'none', 'description' => 'All Services are Operational']]),
        'GET api.taqnyat.sa/account/balance' => $balanceStatus === 201
            ? Http::response(['statusCode' => 200, 'accountStatus' => 'active', 'accountExpiryDate' => '15-03-2027', 'balance' => '100.0000', 'currency' => 'SAR', 'points' => '667'], 201)
            : Http::response(['statusCode' => $balanceStatus, 'message' => 'invalid credentials information.'], $balanceStatus),
        'GET api.taqnyat.sa/v1/messages/senders' => Http::response(['statusCode' => 201, 'senders' => [
            ['senderName' => 'Moashir', 'status' => $senderStatus, 'destination' => 'KSA'],
            ['senderName' => 'Moashir-AD', 'status' => 'inactive', 'destination' => 'KSA'],
        ]], 201),
        'POST api.taqnyat.sa/v1/messages' => Http::response(['statusCode' => 201, 'messageId' => '5829452722', 'cost' => '0.1500', 'currency' => 'SAR', 'totalCount' => 1, 'msgLength' => 1, 'accepted' => '[966551234567,]', 'rejected' => '[]'], 201),
    ]);
}

function useSettingsSmsGateway(): void
{
    config()->set('moashir.sms.driver', 'settings');
    app()->forgetInstance(SmsDriverSelection::class);
    app()->forgetInstance(SmsGateway::class);
}

it('shows both providers, the test numbers and the OTP template without secrets', function (): void {
    configureTaqnyat();
    signIn(systemAdmin());

    $response = $this->getJson(SMS_SETTINGS)
        ->assertOk()
        ->assertJsonPath('data.active_provider', null)
        ->assertJsonPath('data.mode', 'live')
        ->assertJsonPath('data.source', 'settings')
        ->assertJsonPath('data.test_recipients', [])
        ->assertJsonPath('data.providers.taqnyat.configured', true)
        ->assertJsonPath('data.providers.taqnyat.sender_name', 'Moashir')
        ->assertJsonPath('data.providers.taqnyat.bearer_token.set', true)
        ->assertJsonPath('data.providers.taqnyat.bearer_token.last4', '0001')
        ->assertJsonPath('data.providers.msegat.configured', false)
        ->assertJsonPath('data.providers.msegat.api_key.set', false)
        ->assertJsonPath('data.templates.0.key', 'otp_code')
        ->assertJsonPath('data.templates.0.placeholders', ['code', 'minutes'])
        ->assertJsonPath('data.templates.0.body.en', 'Your Moashir verification code is {code}. It is valid for {minutes} minutes.');

    assertNoSecretIn((string) $response->getContent());
});

it('saves the providers, the mode and up to five test numbers and activates one provider', function (): void {
    signIn(systemAdmin());

    $this->putJson(SMS_SETTINGS, [
        'mode' => 'test',
        'test_recipients' => [TEST_NUMBER, '+966561234567'],
        'providers' => ['taqnyat' => ['bearer_token' => FAKE_TAQNYAT_TOKEN, 'sender_name' => 'Moashir']],
        'active_provider' => 'taqnyat',
    ])
        ->assertOk()
        ->assertJsonPath('data.active_provider', 'taqnyat')
        ->assertJsonPath('data.mode', 'test')
        ->assertJsonPath('data.test_recipients', [TEST_NUMBER, '+966561234567']);

    $this->putJson(SMS_SETTINGS, [
        'providers' => ['msegat' => ['username' => 'moashir-account', 'api_key' => FAKE_MSEGAT_KEY, 'sender_name' => 'Moashir']],
        'active_provider' => 'msegat',
    ])->assertOk()->assertJsonPath('data.active_provider', 'msegat');

    expect(IntegrationCredential::query()->where('kind', 'sms')->where('is_active', true)->count())->toBe(1)
        ->and(IntegrationCredential::query()->where('kind', 'sms')->pluck('mode')->map->value->unique()->all())->toBe(['test']);

    $this->putJson(SMS_SETTINGS, ['active_provider' => null])->assertOk()->assertJsonPath('data.active_provider', null);
    assertNoSecretInAudits();
});

it('refuses to activate a provider that is not fully configured', function (): void {
    signIn(systemAdmin());

    $this->putJson(SMS_SETTINGS, ['providers' => ['taqnyat' => ['bearer_token' => FAKE_TAQNYAT_TOKEN]], 'active_provider' => 'taqnyat'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'integration.configuration_incomplete');

    expect(IntegrationCredential::query()->count())->toBe(0);
});

it('validates the SMS settings', function (array $payload, string $field): void {
    signIn(systemAdmin());

    $this->putJson(SMS_SETTINGS, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'six test numbers' => [['test_recipients' => ['+966551234561', '+966551234562', '+966551234563', '+966551234564', '+966551234565', '+966551234566']], 'test_recipients'],
    'local test number' => [['test_recipients' => ['0551234567']], 'test_recipients.0'],
    'duplicate test number' => [['test_recipients' => [TEST_NUMBER, TEST_NUMBER]], 'test_recipients.0'],
    'unknown provider' => [['active_provider' => 'unifonic'], 'active_provider'],
    'short bearer token' => [['providers' => ['taqnyat' => ['bearer_token' => 'short']]], 'providers.taqnyat.bearer_token'],
    'Msegat sender with spaces' => [['providers' => ['msegat' => ['sender_name' => 'Moashir AD']]], 'providers.msegat.sender_name'],
    'Msegat sender too long' => [['providers' => ['msegat' => ['sender_name' => 'MoashirPlatform']]], 'providers.msegat.sender_name'],
    'marketing sender field' => [['providers' => ['msegat' => ['marketing_sender' => 'MoashirAD']]], 'providers.msegat'],
    'template without the code' => [['templates' => [['key' => 'otp_code', 'body' => ['ar' => 'رمز الدخول صالح لمدة {minutes} دقيقة.', 'en' => 'Your code is {code}.']]]], 'templates.0.body.ar'],
    'template with an unknown placeholder' => [['templates' => [['key' => 'otp_code', 'body' => ['ar' => 'رمزك {code}', 'en' => 'Code {code} for {name}']]]], 'templates.0.body.en'],
    'OTP template longer than one SMS' => [['templates' => [['key' => 'otp_code', 'body' => ['ar' => 'رمز التحقق '.str_repeat('مؤشر ', 14).'{code}', 'en' => 'Your code is {code}.']]]], 'templates.0.body.ar'],
    'unknown template' => [['templates' => [['key' => 'welcome', 'body' => ['ar' => 'مرحبًا', 'en' => 'Welcome']]]], 'templates.0.key'],
]);

it('saves an OTP template that the OTP notification then uses', function (): void {
    signIn(systemAdmin());

    $this->putJson(SMS_SETTINGS, ['templates' => [['key' => 'otp_code', 'body' => [
        'ar' => 'رمز الدخول إلى مؤشر: {code}',
        'en' => 'Moashir sign-in code: {code}',
    ]]]])->assertOk()->assertJsonPath('data.templates.0.body.en', 'Moashir sign-in code: {code}');

    $notification = new OtpCodeNotification('482915', OtpChannel::Sms, 5);
    app()->setLocale('en');

    expect($notification->toSms(entityUser()))->toBe('Moashir sign-in code: 482915');
});

it('tests the Taqnyat account, its balance and the configured sender, and raises INT-02', function (): void {
    Notification::fake();
    $otherAdmin = systemAdmin();
    fakeTaqnyatAccount();
    configureTaqnyat();
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/taqnyat/test-connection')
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.checks.*.key', ['service_status', 'credentials_valid', 'account_usable', 'sender_active'])
        ->assertJsonPath('data.info.balance', '100.0000')
        ->assertJsonPath('data.info.currency', 'SAR')
        ->assertJsonPath('data.info.account_status', 'active');

    Http::assertSent(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/account/balance') && $request->hasHeader('Authorization', 'Bearer '.FAKE_TAQNYAT_TOKEN));
    Notification::assertSentTo($otherAdmin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::SmsGatewayTestPassed);
});

it('fails the Taqnyat test when the sender is not active', function (): void {
    fakeTaqnyatAccount(senderStatus: 'inactive');
    configureTaqnyat();
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/taqnyat/test-connection')
        ->assertStatus(502)
        ->assertJsonPath('errors.provider_error_kind', ['misconfigured'])
        ->assertJsonPath('meta.result.checks.3', ['key' => 'sender_active', 'ok' => false, 'detail_code' => 'sender_not_active']);
});

it('maps an invalid Taqnyat token to auth_failed and raises INT-01', function (): void {
    Notification::fake();
    $otherAdmin = systemAdmin();
    fakeTaqnyatAccount(401);
    configureTaqnyat();
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/taqnyat/test-connection')->assertStatus(502)->assertJsonPath('errors.provider_error_kind', ['auth_failed']);

    Notification::assertSentTo($otherAdmin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::SmsSendFailed);
});

it('tests the Msegat balance', function (): void {
    fakeProviderRoutes(['POST www.msegat.com/gw/Credits.php' => Http::response('150.25', 200, ['Content-Type' => 'text/plain'])]);
    configureMsegat();
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/msegat/test-connection')
        ->assertOk()
        ->assertJsonPath('data.provider', 'msegat')
        ->assertJsonPath('data.info.balance', '150.25');

    Http::assertSent(static fn (ClientRequest $request): bool => $request->isMultipart() && str_contains($request->body(), 'moashir-account'));
});

it('maps the Msegat login error to auth_failed', function (): void {
    fakeProviderRoutes(['POST www.msegat.com/gw/Credits.php' => Http::response('M0002', 200, ['Content-Type' => 'text/plain'])]);
    configureMsegat();
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/msegat/test-connection')->assertStatus(502)->assertJsonPath('errors.provider_error_kind', ['auth_failed']);
});

it('loads the Taqnyat senders with the stored token before a sender is chosen', function (): void {
    fakeTaqnyatAccount();
    storeCredential(IntegrationProvider::Taqnyat, null, [], ['bearer_token' => FAKE_TAQNYAT_TOKEN]);
    signIn(systemAdmin());

    $this->getJson(SMS_SETTINGS.'/taqnyat/senders')
        ->assertOk()
        ->assertJsonPath('data', [
            ['sender_name' => 'Moashir', 'status' => 'active', 'destination' => 'KSA'],
            ['sender_name' => 'Moashir-AD', 'status' => 'inactive', 'destination' => 'KSA'],
        ]);
});

it('loads the approved Msegat templates and blocks templates that do not match when the sender is restricted', function (): void {
    fakeProviderRoutes(['POST www.msegat.com/gw/GetMessageTemplates.php' => Http::response(['code' => 'M0000', 'message' => 'Success', 'result' => [
        ['id' => 1234, 'title' => 'OTP ar', 'msgBody' => 'رمز الدخول إلى مؤشر: {{1}}'],
        ['id' => 1235, 'title' => 'OTP en', 'msgBody' => 'Moashir sign-in code: {{1}}'],
    ]])]);
    configureMsegat();
    signIn(systemAdmin());

    $this->putJson(SMS_SETTINGS, ['providers' => ['msegat' => ['template_restricted' => true]]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sms.templates_not_loaded');

    $this->getJson(SMS_SETTINGS.'/msegat/templates')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0', ['id' => '1234', 'title' => 'OTP ar', 'body' => 'رمز الدخول إلى مؤشر: {{1}}']);

    $this->putJson(SMS_SETTINGS, ['providers' => ['msegat' => ['template_restricted' => true]]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sms.template_not_approved')
        ->assertJsonValidationErrors('templates.otp_code.body.ar');

    $this->putJson(SMS_SETTINGS, [
        'providers' => ['msegat' => ['template_restricted' => true]],
        'templates' => [['key' => 'otp_code', 'body' => ['ar' => 'رمز الدخول إلى مؤشر: {code}', 'en' => 'Moashir sign-in code: {code}']]],
    ])
        ->assertOk()
        ->assertJsonPath('data.providers.msegat.template_restricted', true)
        ->assertJsonPath('data.templates.0.matches_msegat_template', ['ar' => true, 'en' => true]);

    expect(Audit::query()->where('event', AuditEvent::SmsTemplatesLoaded->value)->count())->toBe(1);
});

it('sends a test SMS through the chosen provider', function (): void {
    fakeTaqnyatAccount();
    configureTaqnyat();
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/test', ['recipient' => TEST_NUMBER, 'provider' => 'taqnyat'])
        ->assertOk()
        ->assertJsonPath('data', ['provider' => 'taqnyat', 'status' => 'sent', 'provider_message_id' => '5829452722']);

    Http::assertSent(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/v1/messages')
        && $request['recipients'] === ['966551234567'] && $request['sender'] === 'Moashir' && str_contains((string) $request['body'], 'مؤشر'));

    $audit = Audit::query()->where('event', AuditEvent::SmsTestSent->value)->firstOrFail();
    expect(json_encode($audit->new_values))->not->toContain('966551234567');
});

it('sends test SMS in test mode only to the allowed test numbers', function (): void {
    configureTaqnyat();
    setIntegrationSetting(SettingKey::SmsMode, 'test');
    IntegrationCredential::query()->update(['mode' => 'test']);
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/test', ['recipient' => TEST_NUMBER, 'provider' => 'taqnyat'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sms.recipient_not_in_test_numbers');

    Http::assertNothingSent();
});

it('answers 502 with the provider error kind when the test SMS is refused', function (): void {
    fakeProviderRoutes(['POST api.taqnyat.sa/v1/messages' => Http::response(['statusCode' => 400, 'message' => 'Your balance is 0'], 400)]);
    configureTaqnyat(['is_active' => true]);
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/test', ['recipient' => TEST_NUMBER])->assertStatus(502)->assertJsonPath('errors.provider_error_kind', ['quota_exhausted']);
});

it('suppresses runtime messages to other numbers in test mode and sends to the allowed ones', function (): void {
    fakeTaqnyatAccount();
    configureTaqnyat(['is_active' => true]);
    setIntegrationSetting(SettingKey::SmsMode, 'test');
    setIntegrationSetting(SettingKey::SmsTestRecipients, [TEST_NUMBER]);
    IntegrationCredential::query()->update(['mode' => 'test']);
    $gateway = app(SettingsSmsGateway::class);

    $suppressed = $gateway->send(new SmsMessage(new PhoneNumber('+966569999999'), 'رمز التحقق 123456', SmsPurpose::Otp));
    Http::assertNothingSent();
    $sent = $gateway->send(new SmsMessage(new PhoneNumber(TEST_NUMBER), 'رمز التحقق 123456', SmsPurpose::Otp));

    expect($suppressed->status->value)->toBe('suppressed_test_mode')
        ->and($sent->status->value)->toBe('sent');
    Http::assertSentCount(1);
});

it('sends sign-in codes by e-mail while no SMS provider is configured', function (): void {
    Notification::fake();
    useSettingsSmsGateway();
    $user = User::factory()->withRole(RoleKey::Entity)->create(['phone' => '+966512345678']);

    $this->postJson('/api/v1/auth/otp/request', ['email' => $user->email, 'channel' => 'sms'])->assertOk()->assertJsonPath('data.otp.channel', 'email');
});

it('keeps the SMS endpoints for the system administrator', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(SMS_SETTINGS)->assertForbidden();
    $this->putJson(SMS_SETTINGS, ['mode' => 'test'])->assertForbidden();
    $this->postJson(SMS_SETTINGS.'/taqnyat/test-connection')->assertForbidden();
    $this->getJson(SMS_SETTINGS.'/taqnyat/senders')->assertForbidden();
    $this->getJson(SMS_SETTINGS.'/msegat/templates')->assertForbidden();
    $this->postJson(SMS_SETTINGS.'/test', ['recipient' => TEST_NUMBER])->assertForbidden();
})->with(nonSystemAdminRoles());

it('does not route an AI provider through the SMS test endpoint', function (): void {
    signIn(systemAdmin());

    $this->postJson(SMS_SETTINGS.'/openai/test-connection')->assertNotFound();
});
