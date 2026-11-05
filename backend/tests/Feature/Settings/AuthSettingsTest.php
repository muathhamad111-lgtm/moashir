<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Settings\Setting;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

const AUTH_SETTINGS = '/api/v1/settings/auth';

const AUTH_SETTINGS_REASON = 'السماح برمز الدخول عبر الرسائل النصية بطلب موثّق من الجهة المالكة للمنصة.';

beforeEach(function (): void {
    Notification::fake();
});

function isSignInChannelsAlert(SecurityAlertNotification $alert): bool
{
    return $alert->code() === NotificationCode::EntitySignInCodeChannelsChanged;
}

it('shows e-mail as the only entity sign-in code channel by default, the SMS gateway state and staff TOTP as mandatory', function (): void {
    signIn(systemAdmin());

    $this->getJson(AUTH_SETTINGS)
        ->assertOk()
        ->assertExactJson(['data' => [
            'entity_sign_in_code_channels' => ['email'],
            'sms_status' => 'live',
            'staff_totp_required' => true,
        ], 'meta' => [], 'errors' => null]);
});

it('reports the SMS gateway as unavailable or in test mode', function (): void {
    signIn(systemAdmin());

    setIntegrationSetting(SettingKey::SmsMode, IntegrationMode::Test->value);
    $this->getJson(AUTH_SETTINGS)->assertJsonPath('data.sms_status', 'test');

    useSettingsSmsGateway();
    $this->getJson(AUTH_SETTINGS)->assertJsonPath('data.sms_status', 'unavailable');
});

it('lets the System Administrator allow SMS codes, audits the change and alerts every administrator', function (): void {
    config()->set('moashir.security.alert_mail', 'security@moashir.example.sa');
    $actor = systemAdmin();
    $otherAdministrator = systemAdmin();
    $superAdmin = superAdmin();
    signIn($actor);

    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['sms', 'email']])
        ->assertOk()
        ->assertJsonPath('data.entity_sign_in_code_channels', ['email', 'sms']);

    $audit = Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->sole();
    expect($audit->old_values)->toBe([SettingKey::EntitySignInCodeChannels->value => ['email']])
        ->and($audit->new_values)->toBe([SettingKey::EntitySignInCodeChannels->value => ['email', 'sms']])
        ->and($audit->user_id)->toBe($actor->id)
        ->and(Setting::query()->where('key', SettingKey::EntitySignInCodeChannels->value)->value('updated_by_user_id'))->toBe($actor->id);
    Notification::assertSentTo($otherAdministrator, SecurityAlertNotification::class, isSignInChannelsAlert(...));
    Notification::assertSentTo($actor, SecurityAlertNotification::class, isSignInChannelsAlert(...));
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, isSignInChannelsAlert(...));
    Notification::assertSentOnDemand(SecurityAlertNotification::class, static fn (SecurityAlertNotification $alert, array $channels, AnonymousNotifiable $notifiable): bool => isSignInChannelsAlert($alert) && $notifiable->routes['mail'] === 'security@moashir.example.sa');
    $this->getJson('/api/v1/auth/login-modes')->assertJsonPath('data.code_channels', ['email', 'sms']);
});

it('words the SEC-07 alert as a change of the entity sign-in code channels', function (): void {
    $alert = new SecurityAlertNotification(NotificationCode::EntitySignInCodeChannelsChanged);

    expect(__('notifications.security_alert.SEC-07.title', [], 'en'))->toBe('Entity sign-in code channels changed')
        ->and(__('notifications.security_alert.SEC-07.title', [], 'ar'))->toBe('تغيير قنوات رمز دخول الجهات')
        ->and($alert->code()->value)->toBe('SEC-07');
});

it('writes nothing and alerts nobody when the channels do not change', function (): void {
    signIn(systemAdmin());

    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email']])->assertOk();
    $this->putJson(AUTH_SETTINGS, [])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('requires a reason from the Super Admin and records it with the change', function (): void {
    $superAdmin = superAdmin();
    signIn($superAdmin);

    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email', 'sms']])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    expect(Setting::query()->where('key', SettingKey::EntitySignInCodeChannels->value)->exists())->toBeFalse();

    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email', 'sms'], 'reason' => AUTH_SETTINGS_REASON])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->sole()->reason)->toBe(AUTH_SETTINGS_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, isSignInChannelsAlert(...));
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, static fn (SecurityAlertNotification $alert): bool => $alert->code() === NotificationCode::ReasonRequiredAction);
});

it('refuses SMS codes while the SMS gateway is not configured and keeps the saved value', function (): void {
    signIn(systemAdmin());
    allowSignInCodeChannels('email', 'sms');
    useSettingsSmsGateway();

    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email', 'sms']], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.entity_sign_in_code_channels.0', 'Configure the SMS gateway before allowing sign-in codes by text message.');

    $this->getJson(AUTH_SETTINGS)->assertJsonPath('data.entity_sign_in_code_channels', ['email', 'sms']);
    $this->getJson('/api/v1/auth/login-modes')->assertJsonPath('data.code_channels', ['email']);
    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email']])->assertOk()->assertJsonPath('data.entity_sign_in_code_channels', ['email']);
});

it('keeps e-mail as an allowed channel', function (): void {
    signIn(systemAdmin());

    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['sms']])
        ->assertUnprocessable()
        ->assertJsonPath('errors.entity_sign_in_code_channels.0', 'يجب أن يبقى البريد الإلكتروني مسموحًا به، فهو القناة التي تضمن وصول رمز الدخول إلى كل جهة.');

    expect(Setting::query()->where('key', SettingKey::EntitySignInCodeChannels->value)->exists())->toBeFalse();
});

it('denies every role without settings.auth.manage', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(AUTH_SETTINGS)->assertForbidden();
    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email', 'sms']])->assertForbidden();
})->with([RoleKey::Assessor, RoleKey::FinanceOfficer, RoleKey::ContentOfficer, RoleKey::RequestFollower]);

it('denies entity accounts and asks guests to sign in', function (): void {
    signIn(entityUser())->getJson(AUTH_SETTINGS)->assertForbidden();
    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email', 'sms']])->assertForbidden();
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->getJson(AUTH_SETTINGS)->assertUnauthorized();
    $this->putJson(AUTH_SETTINGS, ['entity_sign_in_code_channels' => ['email']])->assertUnauthorized();
});

it('keeps settings.auth.manage locked to the System Administrator', function (): void {
    signIn(staffUser(RoleKey::SystemAdmin));

    $this->putJson('/api/v1/roles/content_officer/permissions', ['permission_keys' => ['dashboard.view', 'settings.auth.manage']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'permission.locked');
});

it('validates the payload', function (array $payload, string $field): void {
    signIn(systemAdmin());

    $this->putJson(AUTH_SETTINGS, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'empty list' => [['entity_sign_in_code_channels' => []], 'entity_sign_in_code_channels'],
    'null' => [['entity_sign_in_code_channels' => null], 'entity_sign_in_code_channels'],
    'not a list' => [['entity_sign_in_code_channels' => 'email'], 'entity_sign_in_code_channels'],
    'unknown channel' => [['entity_sign_in_code_channels' => ['email', 'whatsapp']], 'entity_sign_in_code_channels.1'],
    'duplicate channel' => [['entity_sign_in_code_channels' => ['email', 'email']], 'entity_sign_in_code_channels.0'],
    'too many channels' => [['entity_sign_in_code_channels' => ['email', 'sms', 'email']], 'entity_sign_in_code_channels'],
    'e-mail missing' => [['entity_sign_in_code_channels' => ['sms']], 'entity_sign_in_code_channels'],
    'short reason' => [['entity_sign_in_code_channels' => ['email', 'sms'], 'reason' => 'قصير'], 'reason'],
]);
