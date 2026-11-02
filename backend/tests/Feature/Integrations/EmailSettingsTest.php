<?php

declare(strict_types=1);

use App\Contracts\Integrations\SmtpTransportFactory;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Integrations\IntegrationCredential;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\Support\RecordingSmtpTransportFactory;

const EMAIL_SETTINGS = '/api/v1/settings/email';

const PUBLIC_SMTP_ADDRESS = '62.149.100.10';

/**
 * @return array<string, mixed>
 */
function smtpPayload(array $overrides = []): array
{
    return [
        'host' => 'smtp.moashir.example.sa',
        'port' => 587,
        'encryption' => 'starttls',
        'username' => 'notifications@moashir.example.sa',
        'password' => FAKE_SMTP_PASSWORD,
        'from_address' => 'no-reply@moashir.example.sa',
        'from_name' => ['ar' => 'منصة مؤشر', 'en' => 'Moashir Platform'],
        ...$overrides,
    ];
}

function recordSmtp(?string $failure = null, int $code = 0): RecordingSmtpTransportFactory
{
    $factory = new RecordingSmtpTransportFactory($failure, $code);
    app()->instance(SmtpTransportFactory::class, $factory);

    return $factory;
}

beforeEach(function (): void {
    resolveHosts(['smtp.moashir.example.sa' => [PUBLIC_SMTP_ADDRESS]], [
        'moashir.example.sa' => ['v=spf1 include:_spf.moashir.example.sa -all'],
        '_dmarc.moashir.example.sa' => ['v=DMARC1; p=quarantine; rua=mailto:dmarc@moashir.example.sa'],
    ]);
});

it('saves the SMTP settings, keeps the password write-only and activates e-mail', function (): void {
    signIn(systemAdmin());

    $response = $this->putJson(EMAIL_SETTINGS, [...smtpPayload(), 'active' => true])
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.host', 'smtp.moashir.example.sa')
        ->assertJsonPath('data.port', 587)
        ->assertJsonPath('data.encryption', 'starttls')
        ->assertJsonPath('data.password.set', true)
        ->assertJsonPath('data.password.last4', null)
        ->assertJsonPath('data.from_name', ['ar' => 'منصة مؤشر', 'en' => 'Moashir Platform'])
        ->assertJsonPath('data.timeout_seconds', 15);

    assertNoSecretIn((string) $response->getContent());
    assertNoSecretInAudits();
});

it('refuses mail servers on private, loopback, link-local, shared and unique-local addresses', function (string $address): void {
    resolveHosts(['mail.internal.example.sa' => [$address]]);
    signIn(systemAdmin());

    $this->putJson(EMAIL_SETTINGS, smtpPayload(['host' => 'mail.internal.example.sa']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'email.host_not_allowed')
        ->assertJsonValidationErrors('host');

    expect(IntegrationCredential::query()->count())->toBe(0);
})->with(['10.0.0.5', '172.16.4.2', '192.168.1.10', '127.0.0.1', '169.254.169.254', '100.64.1.2', '0.0.0.0', 'fd00::25', '::1', 'fe80::1', '::ffff:10.0.0.5']);

it('refuses a host name that does not resolve and IP literals', function (string $host, string $code): void {
    signIn(systemAdmin());

    $this->putJson(EMAIL_SETTINGS, smtpPayload(['host' => $host]))->assertUnprocessable()->assertJsonValidationErrors('host')->assertJsonPath('code', $code);
})->with([
    'unknown host' => ['unknown.moashir.example.sa', 'email.host_not_allowed'],
    'IPv4 literal' => ['10.0.0.5', 'validation_failed'],
    'public IPv4 literal' => [PUBLIC_SMTP_ADDRESS, 'validation_failed'],
    'URL instead of host' => ['smtp://smtp.moashir.example.sa', 'validation_failed'],
]);

it('validates the SMTP fields', function (array $overrides, string $field): void {
    signIn(systemAdmin());

    $this->putJson(EMAIL_SETTINGS, smtpPayload($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'port outside the allowed set' => [['port' => 8025], 'port'],
    'encryption none' => [['encryption' => 'none'], 'encryption'],
    'invalid from address' => [['from_address' => 'no-reply'], 'from_address'],
    'missing English sender name' => [['from_name' => ['ar' => 'منصة مؤشر']], 'from_name.en'],
    'timeout too long' => [['timeout_seconds' => 120], 'timeout_seconds'],
    'invalid EHLO domain' => [['ehlo_domain' => 'not a host'], 'ehlo_domain'],
]);

it('needs a test mailbox before switching e-mail to test mode', function (): void {
    configureSmtp();
    signIn(systemAdmin());

    $this->putJson(EMAIL_SETTINGS, ['mode' => 'test'])->assertUnprocessable()->assertJsonPath('code', 'email.test_address_required');

    $this->putJson(EMAIL_SETTINGS, ['mode' => 'test', 'test_address' => 'qa@moashir.example.sa'])
        ->assertOk()
        ->assertJsonPath('data.mode', 'test')
        ->assertJsonPath('data.test_address', 'qa@moashir.example.sa');
});

it('tests the SMTP handshake without sending and reports the advisory DNS records', function (): void {
    $factory = recordSmtp();
    configureSmtp();
    signIn(systemAdmin());

    $this->postJson(EMAIL_SETTINGS.'/test-connection')
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.checks', [['key' => 'host_allowed', 'ok' => true, 'detail_code' => null], ['key' => 'handshake', 'ok' => true, 'detail_code' => null]])
        ->assertJsonPath('data.info.spf_record_found', true)
        ->assertJsonPath('data.info.dmarc_policy', 'quarantine');

    expect($factory->starts)->toBe(1)->and($factory->sent)->toBe([]);
});

it('maps an SMTP authentication failure to auth_failed', function (): void {
    recordSmtp('Expected response code "235" but got code "535", with message "535 5.7.8 Authentication failed".', 535);
    configureSmtp();
    signIn(systemAdmin());

    $this->postJson(EMAIL_SETTINGS.'/test-connection')->assertStatus(502)->assertJsonPath('errors.provider_error_kind', ['auth_failed']);
});

it('sends the test e-mail with the configured sender and redirects it in test mode', function (): void {
    $factory = recordSmtp();
    configureSmtp();
    signIn(systemAdmin());

    $this->postJson(EMAIL_SETTINGS.'/test', ['recipient' => 'it@moashir.example.sa'])
        ->assertOk()
        ->assertJsonPath('data', ['sent' => true, 'redirected_to_test_mailbox' => false]);

    $message = $factory->sent[0];
    expect($message)->toBeInstanceOf(Email::class)
        ->and($message->getTo()[0]->getAddress())->toBe('it@moashir.example.sa')
        ->and($message->getFrom()[0]->getAddress())->toBe('no-reply@moashir.example.sa')
        ->and($message->getFrom()[0]->getName())->toBe('منصة مؤشر')
        ->and($message->getSubject())->toContain('Moashir');

    setIntegrationSetting(SettingKey::EmailTestAddress, 'qa@moashir.example.sa');
    setIntegrationSetting(SettingKey::EmailMode, 'test');
    IntegrationCredential::query()->update(['mode' => 'test']);

    $this->postJson(EMAIL_SETTINGS.'/test', ['recipient' => 'it@moashir.example.sa'])->assertOk()->assertJsonPath('data.redirected_to_test_mailbox', true);

    expect($factory->sent[1]->getTo()[0]->getAddress())->toBe('qa@moashir.example.sa');
});

it('delivers platform mail through the settings mailer with the sender name of the message locale', function (): void {
    $factory = recordSmtp();
    configureSmtp(['is_active' => true]);
    app()->setLocale('en');

    Mail::mailer('settings')->raw('Your Moashir request was received.', static fn ($message) => $message->to('owner@alamal.example.sa')->subject('Request received'));

    expect($factory->sent)->toHaveCount(1)
        ->and($factory->sent[0]->getFrom()[0]->getName())->toBe('Moashir Platform')
        ->and($factory->sent[0]->getTo()[0]->getAddress())->toBe('owner@alamal.example.sa');

    Mail::mailer('settings')->raw('Second message.', static fn ($message) => $message->to('owner@alamal.example.sa'));
    expect($factory->builds)->toBe(1);

    $this->travel(1)->seconds();
    signIn(systemAdmin());
    $this->putJson(EMAIL_SETTINGS, ['test_address' => 'qa@moashir.example.sa', 'mode' => 'test'])->assertOk();

    Mail::mailer('settings')->raw('Third message.', static fn ($message) => $message->to('owner@alamal.example.sa')->cc('copy@alamal.example.sa'));

    expect($factory->builds)->toBe(2)
        ->and($factory->sent[2]->getTo()[0]->getAddress())->toBe('qa@moashir.example.sa')
        ->and($factory->sent[2]->getCc())->toBe([]);
});

it('fails safely and alerts when platform mail is sent without e-mail settings', function (): void {
    Notification::fake();
    $admin = systemAdmin();

    expect(fn () => Mail::mailer('settings')->raw('Message.', static fn ($message) => $message->to('owner@alamal.example.sa')))->toThrow(TransportException::class);

    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::ExternalServiceDown);
});

it('keeps the e-mail endpoints for the system administrator', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(EMAIL_SETTINGS)->assertForbidden();
    $this->putJson(EMAIL_SETTINGS, ['mode' => 'live'])->assertForbidden();
    $this->postJson(EMAIL_SETTINGS.'/test-connection')->assertForbidden();
    $this->postJson(EMAIL_SETTINGS.'/test', ['recipient' => 'it@moashir.example.sa'])->assertForbidden();
})->with(nonSystemAdminRoles());
