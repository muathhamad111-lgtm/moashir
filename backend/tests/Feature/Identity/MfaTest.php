<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

function beginStaffSignIn(User $user): void
{
    test()->postJson('/api/v1/auth/login', ['email' => $user->email, 'mode' => 'password', 'password' => defaultPassword()])->assertOk();
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

it('rejects a wrong code and abandons the sign-in after five failures', function (): void {
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

it('lets an entity user enable and disable TOTP with the current password', function (): void {
    $user = entityUser();
    signIn($user);

    $key = $this->postJson('/api/v1/auth/mfa/enroll')->assertOk()->json('data.manual_entry_key');
    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => (new Google2FA)->getCurrentOtp($key)])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');

    $this->deleteJson('/api/v1/auth/mfa', ['current_password' => 'Wrong-Password-2026!'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->deleteJson('/api/v1/auth/mfa', ['current_password' => defaultPassword()])->assertNoContent();

    expect($user->refresh()->hasConfirmedTotp())->toBeFalse();
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
