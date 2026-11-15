<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;

const TWO_FACTOR_SETTINGS = '/api/v1/settings/auth';

beforeEach(function (): void {
    Notification::fake();
});

function isTwoFactorAlert(SecurityAlertNotification $alert): bool
{
    return $alert->code() === NotificationCode::TwoFactorPolicyChanged;
}

it('requires two-factor for every staff role and no entity by default', function (): void {
    signIn(systemAdmin());

    $this->getJson(TWO_FACTOR_SETTINGS)
        ->assertOk()
        ->assertJsonPath('data.two_factor_enabled', true)
        ->assertJsonPath('data.two_factor_roles', RoleKey::staffValues());
});

it('limits two-factor to the chosen roles, recomputes every account, audits and alerts the administrators', function (): void {
    $actor = systemAdmin();
    $assessor = User::factory()->withRole(RoleKey::Assessor)->create(['mfa_required' => true]);
    $owner = ownerOf(entityWithOwner());
    signIn($actor);

    $this->putJson(TWO_FACTOR_SETTINGS, ['two_factor_roles' => ['system_admin', 'entity']])
        ->assertOk()
        ->assertJsonPath('data.two_factor_roles', ['system_admin', 'entity']);

    expect($assessor->refresh()->mfa_required)->toBeFalse()
        ->and($owner->refresh()->mfa_required)->toBeTrue()
        ->and($owner->mayUseSignInCode())->toBeFalse()
        ->and($actor->refresh()->mfa_required)->toBeTrue();
    $audit = Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->sole();
    expect($audit->new_values)->toBe([SettingKey::TwoFactorRoles->value => ['system_admin', 'entity']]);
    Notification::assertSentTo($actor, SecurityAlertNotification::class, isTwoFactorAlert(...));
});

it('turns two-factor off for every role except the Super Admin', function (): void {
    $actor = systemAdmin();
    $superAdmin = superAdmin();
    signIn($actor);

    $this->putJson(TWO_FACTOR_SETTINGS, ['two_factor_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.two_factor_enabled', false);

    expect($actor->refresh()->mfa_required)->toBeFalse()
        ->and($actor->requiresMfa())->toBeFalse()
        ->and($superAdmin->refresh()->requiresMfa())->toBeTrue();
});

it('creates new entity accounts with two-factor when the entity role requires it', function (): void {
    signIn(systemAdmin());
    $this->putJson(TWO_FACTOR_SETTINGS, ['two_factor_roles' => ['entity']])->assertOk();
    auth()->forgetGuards();

    $this->postJson('/api/v1/auth/register', registrationPayload())->assertCreated();

    expect(User::query()->where('email', registrationPayload()['official_email'])->sole()->mfa_required)->toBeTrue();
});

it('lets an entity account enrol an authenticator once two-factor is required for it', function (): void {
    $owner = ownerOf(entityWithOwner());
    signIn(systemAdmin());
    $this->putJson(TWO_FACTOR_SETTINGS, ['two_factor_roles' => ['entity']])->assertOk();
    expect($owner->refresh()->mfa_required)->toBeTrue();
    auth()->forgetGuards();

    signInWith($owner->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'mfa_enrollment');
    $this->postJson('/api/v1/auth/mfa/enroll')
        ->assertOk()
        ->assertJsonStructure(['data' => ['qr_svg', 'manual_entry_key']]);
});

it('writes nothing and alerts nobody when the policy does not change', function (): void {
    $actor = systemAdmin();
    signIn($actor);

    $this->putJson(TWO_FACTOR_SETTINGS, ['two_factor_enabled' => true, 'two_factor_roles' => RoleKey::staffValues()])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::AuthSettingsUpdated->value)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('validates the two-factor payload', function (array $payload, string $field): void {
    signIn(systemAdmin());

    $this->putJson(TWO_FACTOR_SETTINGS, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'enabled not a boolean' => [['two_factor_enabled' => 'yes'], 'two_factor_enabled'],
    'roles not a list' => [['two_factor_roles' => 'system_admin'], 'two_factor_roles'],
    'unknown role' => [['two_factor_roles' => ['captain']], 'two_factor_roles.0'],
    'duplicate role' => [['two_factor_roles' => ['assessor', 'assessor']], 'two_factor_roles.0'],
]);

it('denies the two-factor policy to staff without settings.auth.manage', function (): void {
    signIn(staffUser(RoleKey::ContentOfficer));

    $this->putJson(TWO_FACTOR_SETTINGS, ['two_factor_enabled' => false])->assertForbidden();
});
