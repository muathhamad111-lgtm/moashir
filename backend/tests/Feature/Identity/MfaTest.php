<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountLockedNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

function beginStaffSignIn(User $user): void
{
    signInWith($user->email, defaultPassword())->assertOk();
}

it('completes the staff sign-in with a valid TOTP code', function (): void {
    $user = systemAdmin();
    beginStaffSignIn($user);

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => currentTotp()])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.mfa_enabled', true);

    $this->assertAuthenticatedAs($user, 'web');
});

it('accepts a recovery code only once', function (): void {
    $user = systemAdmin();
    beginStaffSignIn($user);

    $this->postJson('/api/v1/auth/mfa/challenge', ['recovery_code' => 'recovery-code-one'])->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    beginStaffSignIn($user);
    $this->postJson('/api/v1/auth/mfa/challenge', ['recovery_code' => 'recovery-code-one'])->assertUnprocessable()->assertJsonPath('code', 'mfa.invalid');
});

it('rejects a wrong code and abandons the sign-in after five failures when the account lockout allows more', function (): void {
    config()->set('moashir.auth.lockout.max_attempts', 10);
    $user = systemAdmin();
    beginStaffSignIn($user);

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '000000'])->assertJsonPath('code', 'mfa.invalid');
    }

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '000000'])->assertStatus(423)->assertJsonPath('code', 'mfa.attempts_exceeded');
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => currentTotp()])->assertUnauthorized()->assertJsonPath('code', 'mfa.not_pending');
    $this->assertGuest('web');
});

it('counts wrong TOTP codes toward the account lockout across pending sign-ins and addresses', function (): void {
    Notification::fake();
    $administrator = systemAdmin();
    $user = staffUser(RoleKey::Assessor);

    foreach (['10.2.0.1', '10.2.0.2'] as $address) {
        $this->flushSession();
        $this->withServerVariables(['REMOTE_ADDR' => $address]);
        beginStaffSignIn($user);
        $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '000000'])->assertJsonPath('code', 'mfa.invalid');
        $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '000000'])->assertJsonPath('code', 'mfa.invalid');
    }

    $this->flushSession();
    $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.3']);
    beginStaffSignIn($user);

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '000000'])
        ->assertStatus(423)
        ->assertJsonPath('code', 'account_locked');

    expect($user->refresh()->isLocked())->toBeTrue();
    Notification::assertSentTo($administrator, AccountLockedNotification::class);
    signInWith($user->email, defaultPassword())->assertStatus(423)->assertJsonPath('code', 'account_locked');
    $this->assertGuest('web');
});

it('refuses the TOTP challenge of a pending sign-in once the account is locked', function (): void {
    $user = systemAdmin();
    beginStaffSignIn($user);
    $user->forceFill(['locked_until' => now()->addMinutes(15)])->save();

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => currentTotp()])->assertStatus(423)->assertJsonPath('code', 'account_locked');
    $this->assertGuest('web');
});

it('resets the failure counter after a successful TOTP sign-in', function (): void {
    $user = systemAdmin();
    beginStaffSignIn($user);
    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '000000'])->assertJsonPath('code', 'mfa.invalid');

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => currentTotp()])->assertOk();

    expect($user->refresh()->failed_login_attempts)->toBe(0);
});

it('requires a pending sign-in for the challenge', function (): void {
    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '123456'])->assertUnauthorized()->assertJsonPath('code', 'mfa.not_pending');
});

it('expires the pending sign-in after ten minutes', function (): void {
    $user = systemAdmin();
    beginStaffSignIn($user);

    $this->travel(11)->minutes();

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => currentTotp()])->assertUnauthorized();
});

it('alerts the Super Admins when a Super Admin fails the TOTP challenge', function (): void {
    Notification::fake();
    $superAdmin = superAdmin();
    beginStaffSignIn($superAdmin);

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '000000'])->assertUnprocessable();

    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, static fn (SecurityAlertNotification $alert): bool => $alert->code()->value === 'SEC-02');
});

it('validates the challenge payload', function (): void {
    beginStaffSignIn(systemAdmin());

    $this->postJson('/api/v1/auth/mfa/challenge', [])->assertUnprocessable()->assertJsonValidationErrors(['code', 'recovery_code']);
    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => '12ab56'])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('enrols TOTP during the first staff sign-in and returns recovery codes once', function (): void {
    $user = User::factory()->withRole(RoleKey::Assessor)->create();
    beginStaffSignIn($user);

    $enrollment = $this->postJson('/api/v1/auth/mfa/enroll')
        ->assertOk()
        ->assertJsonStructure(['data' => ['qr_svg', 'manual_entry_key']])
        ->json('data');

    $code = (new Google2FA)->getCurrentOtp($enrollment['manual_entry_key']);

    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonCount(8, 'data.recovery_codes');

    $this->assertAuthenticatedAs($user, 'web');
    expect($user->refresh()->hasConfirmedTotp())->toBeTrue();
});

it('rejects a wrong enrolment code', function (): void {
    $user = User::factory()->withRole(RoleKey::Assessor)->create();
    beginStaffSignIn($user);
    $this->postJson('/api/v1/auth/mfa/enroll')->assertOk();

    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => '000000'])->assertUnprocessable()->assertJsonPath('code', 'mfa.invalid');
    $this->assertGuest('web');
});

it('refuses enrolment without a pending sign-in or a session', function (): void {
    $this->postJson('/api/v1/auth/mfa/enroll')->assertUnauthorized()->assertJsonPath('code', 'mfa.not_pending');
});

it('refuses authenticator enrolment for an entity account', function (): void {
    $owner = ownerOf(entityWithOwner());
    signIn($owner);

    $this->postJson('/api/v1/auth/mfa/enroll', ['current_password' => defaultPassword()])->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => '123456'])->assertForbidden()->assertJsonPath('code', 'forbidden');

    expect($owner->refresh()->two_factor_secret)->toBeNull()
        ->and($owner->two_factor_confirmed_at)->toBeNull();
});

it('lets an entity account that still holds an authenticator from before remove it', function (): void {
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(totpAttributes())->save();
    signIn($owner);

    $this->deleteJson('/api/v1/auth/mfa', ['current_password' => 'Wrong-Password-2026!'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->deleteJson('/api/v1/auth/mfa', ['current_password' => defaultPassword()])->assertNoContent();

    expect($owner->refresh()->hasConfirmedTotp())->toBeFalse();
});

it('keeps the staff enrolment for a signed-in staff account that has no authenticator yet', function (): void {
    $user = User::factory()->withRole(RoleKey::Assessor)->create();
    signIn($user);

    $key = $this->postJson('/api/v1/auth/mfa/enroll', ['current_password' => defaultPassword()])->assertOk()->json('data.manual_entry_key');
    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => (new Google2FA)->getCurrentOtp($key)])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');

    expect($user->refresh()->hasConfirmedTotp())->toBeTrue();
});

it('refuses enrolment for a signed-in session past its idle timeout', function (): void {
    $user = entityUser();
    signIn($user);

    $this->travel(61)->minutes();

    $this->postJson('/api/v1/auth/mfa/enroll', ['current_password' => defaultPassword()])->assertUnauthorized()->assertJsonPath('code', 'session.expired');
    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => '123456'])->assertUnauthorized();
    expect($user->refresh()->two_factor_secret)->toBeNull();
});

it('never lets staff disable TOTP', function (): void {
    signIn(systemAdmin());

    $this->deleteJson('/api/v1/auth/mfa', ['current_password' => defaultPassword()])->assertForbidden()->assertJsonPath('code', 'mfa.required_for_role');
});

it('regenerates recovery codes after a password confirmation', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/auth/mfa/recovery-codes', [])->assertUnprocessable();
    $this->postJson('/api/v1/auth/mfa/recovery-codes', ['current_password' => defaultPassword()])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');
});

it('blocks every protected endpoint for staff whose TOTP was removed', function (): void {
    $user = User::factory()->withRole(RoleKey::SystemAdmin)->create();
    signIn($user);

    $this->getJson('/api/v1/auth/session')->assertForbidden()->assertJsonPath('code', 'mfa.required');
    $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'mfa.required');
});
