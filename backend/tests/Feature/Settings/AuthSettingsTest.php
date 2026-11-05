<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Settings\Setting;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

const AUTH_SETTINGS = '/api/v1/settings/auth';

const AUTH_SETTINGS_REASON = 'إيقاف التحقق الإضافي مؤقتًا بطلب موثّق من الجهة المالكة للمنصة.';

beforeEach(function (): void {
    Notification::fake();
});

function isEntitySecondFactorAlert(SecurityAlertNotification $alert): bool
{
    return $alert->code() === NotificationCode::EntitySecondFactorChanged;
}

it('shows the entity second factor on by default and staff TOTP as mandatory', function (): void {
    signIn(systemAdmin());

    $this->getJson(AUTH_SETTINGS)
        ->assertOk()
        ->assertExactJson(['data' => [
            'entity_second_factor_enabled' => true,
            'entity_second_factor_channel' => 'email',
            'staff_totp_required' => true,
        ], 'meta' => [], 'errors' => null]);
});

it('lets the System Administrator turn the entity second factor off, audits it and alerts every administrator', function (): void {
    config()->set('moashir.security.alert_mail', 'security@moashir.example.sa');
    $actor = systemAdmin();
    $otherAdministrator = systemAdmin();
    $superAdmin = superAdmin();
    signIn($actor);

    $this->putJson(AUTH_SETTINGS, ['entity_second_factor_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.entity_second_factor_enabled', false);

    $audit = Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->sole();
    expect($audit->old_values)->toBe([SettingKey::EntitySecondFactorEnabled->value => true])
        ->and($audit->new_values)->toBe([SettingKey::EntitySecondFactorEnabled->value => false])
        ->and($audit->user_id)->toBe($actor->id)
        ->and(Setting::query()->where('key', SettingKey::EntitySecondFactorEnabled->value)->value('updated_by_user_id'))->toBe($actor->id);
    Notification::assertSentTo($otherAdministrator, SecurityAlertNotification::class, isEntitySecondFactorAlert(...));
    Notification::assertSentTo($actor, SecurityAlertNotification::class, isEntitySecondFactorAlert(...));
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, isEntitySecondFactorAlert(...));
    Notification::assertSentOnDemand(SecurityAlertNotification::class, static fn (SecurityAlertNotification $alert, array $channels, AnonymousNotifiable $notifiable): bool => isEntitySecondFactorAlert($alert) && $notifiable->routes['mail'] === 'security@moashir.example.sa');
    $this->getJson('/api/v1/auth/login-modes')->assertJsonPath('data.entity_second_factor_enabled', false);
});

it('writes nothing and alerts nobody when the value does not change', function (): void {
    signIn(systemAdmin());

    $this->putJson(AUTH_SETTINGS, ['entity_second_factor_enabled' => true])->assertOk();
    $this->putJson(AUTH_SETTINGS, [])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('requires a reason from the Super Admin and records it with the change', function (): void {
    $superAdmin = superAdmin();
    signIn($superAdmin);

    $this->putJson(AUTH_SETTINGS, ['entity_second_factor_enabled' => false])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    expect(Setting::query()->where('key', SettingKey::EntitySecondFactorEnabled->value)->exists())->toBeFalse();

    $this->putJson(AUTH_SETTINGS, ['entity_second_factor_enabled' => false, 'reason' => AUTH_SETTINGS_REASON])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->sole()->reason)->toBe(AUTH_SETTINGS_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, isEntitySecondFactorAlert(...));
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, static fn (SecurityAlertNotification $alert): bool => $alert->code() === NotificationCode::ReasonRequiredAction);
});

it('denies every role without settings.auth.manage and asks guests to sign in', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(AUTH_SETTINGS)->assertForbidden();
    $this->putJson(AUTH_SETTINGS, ['entity_second_factor_enabled' => false])->assertForbidden();
})->with([RoleKey::Assessor, RoleKey::FinanceOfficer, RoleKey::ContentOfficer, RoleKey::RequestFollower]);

it('denies entity accounts and guests', function (): void {
    signIn(entityUser())->getJson(AUTH_SETTINGS)->assertForbidden();
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->getJson(AUTH_SETTINGS)->assertUnauthorized();
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
    'not a boolean' => [['entity_second_factor_enabled' => 'sometimes'], 'entity_second_factor_enabled'],
    'null' => [['entity_second_factor_enabled' => null], 'entity_second_factor_enabled'],
    'short reason' => [['entity_second_factor_enabled' => false, 'reason' => 'قصير'], 'reason'],
]);
