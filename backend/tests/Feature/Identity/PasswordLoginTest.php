<?php

declare(strict_types=1);

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountLockedNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

function passwordLogin(string $identifier, string $password): TestResponse
{
    return signInWith($identifier, $password);
}

it('signs an entity user in and returns the session user', function (): void {
    $user = entityUser();

    passwordLogin($user->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.is_super_admin', false)
        ->assertJsonPath('data.user.roles.0.key', 'entity')
        ->assertJsonMissingPath('data.user.password');

    $this->assertAuthenticatedAs($user, 'web');
    expect(AuthenticationEvent::query()->where('user_id', $user->id)->where('type', AuthEventType::LoginSucceeded)->exists())->toBeTrue();
});

it('accepts the email in any letter case', function (): void {
    $user = entityUser();

    passwordLogin(mb_strtoupper($user->email), defaultPassword())->assertOk()->assertJsonPath('data.step', 'authenticated');
});

it('requires a TOTP challenge before signing staff in', function (): void {
    $user = systemAdmin();

    passwordLogin($user->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'mfa_challenge')
        ->assertJsonPath('data.mfa_required', true)
        ->assertJsonMissingPath('data.user');

    $this->assertGuest('web');
});

it('requires TOTP enrolment for staff who have not set it up', function (): void {
    $user = User::factory()->withRole(RoleKey::Assessor)->create();

    passwordLogin($user->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'mfa_enrollment')
        ->assertJsonPath('data.mfa_enrollment_required', true);

    $this->assertGuest('web');
});

it('returns the same generic error for a wrong password and an unknown email', function (): void {
    $user = entityUser();

    $wrongPassword = passwordLogin($user->email, 'Wrong-Password-2026!')->assertUnprocessable()->json();
    $unknownEmail = passwordLogin('no-account@moashir.example.sa', 'Wrong-Password-2026!')->assertUnprocessable()->json();

    expect($wrongPassword)->toBe($unknownEmail)
        ->and($wrongPassword['code'])->toBe('credentials.invalid');
    $this->assertGuest('web');
});

it('still accepts the transitional email field of the previous sign-in form', function (): void {
    $user = entityUser();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'mode' => 'password', 'password' => defaultPassword()])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated');
});

it('ignores the retired passwordless mode of the login endpoint', function (): void {
    $user = entityUser();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'mode' => 'otp', 'channel' => 'email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
    $this->assertGuest('web');
});

it('signs an entity in directly after the password without any code step', function (): void {
    Notification::fake();
    $user = entityUser();

    passwordLogin($user->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'authenticated')->assertJsonMissingPath('data.otp');

    $this->assertAuthenticatedAs($user, 'web');
    Notification::assertNothingSentTo($user);
    expect(AuthenticationEvent::query()->where('user_id', $user->id)->where('type', AuthEventType::LoginSucceeded)->sole()->meta)->toBe(['method' => 'password']);
});

it('locks the account for fifteen minutes after five failed attempts and alerts the administrators', function (): void {
    Notification::fake();
    $administrator = systemAdmin();
    $user = entityUser();

    foreach (range(1, 4) as $attempt) {
        passwordLogin($user->email, 'Wrong-Password-2026!')->assertJsonPath('code', 'credentials.invalid');
    }

    passwordLogin($user->email, 'Wrong-Password-2026!')
        ->assertStatus(423)
        ->assertJsonPath('code', 'account_locked')
        ->assertHeader('Retry-After', '900');

    expect($user->refresh()->isLocked())->toBeTrue();
    Notification::assertSentTo($user, AccountLockedNotification::class, static fn ($notification, array $channels): bool => $channels === ['mail']);
    Notification::assertSentTo($administrator, AccountLockedNotification::class);
});

it('rejects the correct password while the account is locked', function (): void {
    $user = User::factory()->withRole(RoleKey::Entity)->locked()->create();

    passwordLogin($user->email, defaultPassword())->assertStatus(423)->assertJsonPath('code', 'account_locked');
});

it('lets the user sign in again once the lockout has elapsed', function (): void {
    $user = User::factory()->withRole(RoleKey::Entity)->locked()->create();

    $this->travel(16)->minutes();

    passwordLogin($user->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'authenticated');
    expect($user->refresh()->locked_until)->toBeNull();
});

it('rejects a suspended staff account after the password check', function (): void {
    $user = User::factory()->withRole(RoleKey::Assessor)->suspended()->create();

    passwordLogin($user->email, defaultPassword())->assertForbidden()->assertJsonPath('code', 'account_deactivated');
});

it('treats an invited account without a password as invalid credentials', function (): void {
    $user = User::factory()->withRole(RoleKey::Assessor)->invited()->create();

    passwordLogin($user->email, 'Any-Password-2026!')->assertUnprocessable()->assertJsonPath('code', 'credentials.invalid');
});

it('validates the login payload', function (array $payload, string $field): void {
    $this->postJson('/api/v1/auth/login', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($field);
})->with([
    'missing identifier' => [['password' => 'x'], 'identifier'],
    'malformed e-mail' => [['identifier' => 'not-an-email@', 'password' => 'x'], 'identifier'],
    'landline number' => [['identifier' => '0114567890', 'password' => 'x'], 'identifier'],
    'unified number not starting with 7' => [['identifier' => '6001234567', 'password' => 'x'], 'identifier'],
    'unified number with nine digits' => [['identifier' => '700123456', 'password' => 'x'], 'identifier'],
    'Arabic-Indic digits' => [['identifier' => '٧٠٠١٢٣٤٥٦٧', 'password' => 'x'], 'identifier'],
    'identifier too long' => [['identifier' => str_repeat('a', 250).'@example.sa', 'password' => 'x'], 'identifier'],
    'missing password' => [['identifier' => 'person@moashir.example.sa'], 'password'],
]);

it('returns validation messages in the requested language', function (): void {
    $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'en'])
        ->assertJsonPath('message', 'The submitted data is invalid.')
        ->assertJsonPath('errors.identifier.0', 'The e-mail, mobile number or unified national number field is required.');

    $this->postJson('/api/v1/auth/login', [])
        ->assertJsonPath('message', 'البيانات المرسلة غير صالحة.')
        ->assertJsonPath('errors.identifier.0', 'حقل البريد الإلكتروني أو رقم الجوال أو الرقم الوطني الموحد مطلوب.');
});

it('rate limits sign-in attempts per email and address', function (): void {
    foreach (range(1, 5) as $attempt) {
        passwordLogin('rate-limited@moashir.example.sa', 'Wrong-Password-2026!');
    }

    passwordLogin('rate-limited@moashir.example.sa', 'Wrong-Password-2026!')
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});

it('alerts every Super Admin when a Super Admin finishes signing in', function (): void {
    Notification::fake();
    $superAdmin = superAdmin();

    passwordLogin($superAdmin->email, defaultPassword())->assertJsonPath('data.step', 'mfa_challenge');
    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => currentTotp()])
        ->assertOk()
        ->assertJsonPath('data.user.is_super_admin', true);

    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});
