<?php

declare(strict_types=1);

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountLockedNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Contracts\Hashing\Hasher;
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
    $this->freezeSecond();
    $administrator = systemAdmin();
    $user = entityUser();

    foreach (range(1, 4) as $attempt) {
        passwordLogin($user->email, 'Wrong-Password-2026!')->assertJsonPath('code', 'credentials.invalid');
    }

    passwordLogin($user->email, 'Wrong-Password-2026!')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'credentials.invalid')
        ->assertHeaderMissing('Retry-After');

    expect($user->refresh()->isLocked())->toBeTrue()
        ->and($user->locked_until?->toIso8601String())->toBe(now()->addMinutes(15)->toIso8601String());
    Notification::assertSentTo($user, AccountLockedNotification::class, static fn ($notification, array $channels): bool => $channels === ['mail']);
    Notification::assertSentTo($administrator, AccountLockedNotification::class);
});

it('rejects the correct password while the account is locked without extending the lock', function (): void {
    Notification::fake();
    $user = User::factory()->withRole(RoleKey::Entity)->locked()->create();
    $lockedUntil = $user->locked_until?->toIso8601String();

    passwordLogin($user->email, defaultPassword())->assertUnprocessable()->assertJsonPath('code', 'credentials.invalid');

    $this->assertGuest('web');
    expect($user->refresh()->locked_until?->toIso8601String())->toBe($lockedUntil)
        ->and($user->failed_login_attempts)->toBe(0)
        ->and(AuthenticationEvent::query()->where('user_id', $user->id)->where('type', AuthEventType::LoginFailed)->sole()->meta)->toEqual(['identifier_type' => 'email', 'locked' => true]);
    Notification::assertNothingSent();
});

it('answers a locked account exactly like an unknown one whatever password is tried', function (string $password): void {
    $user = User::factory()->withRole(RoleKey::Entity)->locked()->create();

    $locked = passwordLogin($user->email, $password)->assertUnprocessable();
    $unknown = passwordLogin('no-account@moashir.example.sa', $password)->assertUnprocessable();

    expect($locked->json())->toBe($unknown->json())
        ->and($locked->headers->get('Retry-After'))->toBe($unknown->headers->get('Retry-After'));
})->with([
    'the right password' => [defaultPassword()],
    'a wrong password' => ['Wrong-Password-2026!'],
]);

it('gives an unknown and a known account the same answers across a full series of failed attempts', function (): void {
    $user = entityUser();

    foreach (range(1, 6) as $attempt) {
        $known = passwordLogin($user->email, 'Wrong-Password-2026!');
        $unknown = passwordLogin('no-account@moashir.example.sa', 'Wrong-Password-2026!');

        expect($known->status())->toBe($unknown->status())
            ->and($known->json())->toBe($unknown->json())
            ->and($known->headers->get('Retry-After'))->toBe($unknown->headers->get('Retry-After'));
    }

    expect($user->refresh()->isLocked())->toBeTrue();
});

it('spends one password hash on an unknown account, as on a known one, so timing does not reveal it', function (string $identifier): void {
    $user = entityUser();
    $hasher = new class(app('hash')) implements Hasher
    {
        public int $operations = 0;

        public function __construct(private readonly Hasher $inner) {}

        public function info($hashedValue): array
        {
            return $this->inner->info($hashedValue);
        }

        public function make($value, array $options = []): string
        {
            $this->operations++;

            return $this->inner->make($value, $options);
        }

        public function check($value, $hashedValue, array $options = []): bool
        {
            $this->operations++;

            return $this->inner->check($value, $hashedValue, $options);
        }

        public function needsRehash($hashedValue, array $options = []): bool
        {
            return $this->inner->needsRehash($hashedValue, $options);
        }
    };
    app()->instance(Hasher::class, $hasher);

    passwordLogin($identifier === 'known' ? $user->email : 'no-account@moashir.example.sa', 'Wrong-Password-2026!')->assertUnprocessable();

    expect($hasher->operations)->toBe(1);
})->with(['known', 'unknown']);

it('keeps the lock tied to the account so another address cannot sign in with the right password', function (): void {
    $user = entityUser();

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.1'])->postJson('/api/v1/auth/login', ['identifier' => $user->email, 'password' => 'Wrong-Password-2026!']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.2'])
        ->postJson('/api/v1/auth/login', ['identifier' => $user->email, 'password' => defaultPassword()])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'credentials.invalid');
    $this->assertGuest('web');
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
    'nine digits' => [['identifier' => '011456789', 'password' => 'x'], 'identifier'],
    'eleven digits' => [['identifier' => '60012345678', 'password' => 'x'], 'identifier'],
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
