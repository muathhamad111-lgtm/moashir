<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetLinkNotification;
use Database\Factories\Identity\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

const NEW_PASSWORD = 'Wadi-Hanifa-Spring-2026';

function captureResetToken(User $user): string
{
    $token = '';

    Notification::assertSentTo($user, PasswordResetLinkNotification::class, function (PasswordResetLinkNotification $notification) use ($user, &$token): bool {
        $url = (string) $notification->toMail($user)->actionUrl;
        $token = basename($url);

        return true;
    });

    return $token;
}

beforeEach(function (): void {
    Notification::fake();
});

it('always answers a reset request with the same generic message', function (): void {
    $user = entityUser();

    $known = $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk()->json();
    $unknown = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@moashir.example.sa'])->assertOk()->json();

    expect($known)->toBe($unknown);
    Notification::assertSentTo($user, PasswordResetLinkNotification::class);
    Notification::assertSentTimes(PasswordResetLinkNotification::class, 1);
});

it('never puts the email in the reset link', function (): void {
    $user = entityUser();
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);

    Notification::assertSentTo($user, PasswordResetLinkNotification::class, static function (PasswordResetLinkNotification $notification) use ($user): bool {
        $url = (string) $notification->toMail($user)->actionUrl;

        return ! str_contains($url, $user->email) && str_contains($url, '/auth/reset-password/');
    });
});

it('resets the password with a valid single-use token and ends every session', function (): void {
    $user = entityUser();
    DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);
    $token = captureResetToken($user);

    $payload = ['token' => $token, 'email' => $user->email, 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD];

    $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();

    expect(Hash::check(NEW_PASSWORD, (string) $user->refresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    $replay = [...$payload, 'password' => 'Another-Riyadh-Season-2026', 'password_confirmation' => 'Another-Riyadh-Season-2026'];
    $this->postJson('/api/v1/auth/password/reset', $replay)->assertUnprocessable()->assertJsonPath('code', 'token.invalid');
});

it('rejects an expired reset token after 60 minutes', function (): void {
    $user = entityUser();
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);
    $token = captureResetToken($user);

    $this->travel(61)->minutes();

    $this->postJson('/api/v1/auth/password/reset', ['token' => $token, 'email' => $user->email, 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'token.invalid');
});

it('rejects a forged token', function (): void {
    $user = entityUser();

    $this->postJson('/api/v1/auth/password/reset', ['token' => 'forged-token', 'email' => $user->email, 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'token.invalid');
});

it('enforces the password policy on reset', function (string $password, string $message): void {
    markPasswordBreached('Riyadh-Breached-2026');
    $user = entityUser();

    $this->postJson('/api/v1/auth/password/reset', ['token' => 'any', 'email' => $user->email, 'password' => $password, 'password_confirmation' => $password], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', $message);
})->with([
    'shorter than twelve characters' => ['Short-2026', 'The password field must be at least 12 characters.'],
    'found in a breach list' => ['Riyadh-Breached-2026', 'This password has appeared in a known data breach. Please choose a different password.'],
    'the current password' => [UserFactoryPassword::VALUE, 'You cannot reuse any of your last five passwords.'],
]);

it('requires the password confirmation', function (): void {
    $this->postJson('/api/v1/auth/password/reset', ['token' => 'any', 'email' => 'person@moashir.example.sa', 'password' => NEW_PASSWORD, 'password_confirmation' => 'different-value'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('changes the password, keeps the current session and ends the others', function (): void {
    $user = entityUser();
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    signIn($user);

    $this->postJson('/api/v1/auth/password/change', ['current_password' => defaultPassword(), 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertNoContent();

    expect(Hash::check(NEW_PASSWORD, (string) $user->refresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse()
        ->and($user->passwordHistories()->count())->toBe(1);
    $this->assertAuthenticatedAs($user, 'web');
});

it('refuses any of the last five passwords', function (): void {
    $user = entityUser();
    signIn($user);
    $passwords = ['Najd-Plateau-2026-a', 'Najd-Plateau-2026-b', 'Najd-Plateau-2026-c', 'Najd-Plateau-2026-d', 'Najd-Plateau-2026-e', 'Najd-Plateau-2026-f'];
    $current = defaultPassword();

    foreach ($passwords as $next) {
        $this->postJson('/api/v1/auth/password/change', ['current_password' => $current, 'password' => $next, 'password_confirmation' => $next])->assertNoContent();
        $current = $next;
    }

    $this->postJson('/api/v1/auth/password/change', ['current_password' => $current, 'password' => 'Najd-Plateau-2026-b', 'password_confirmation' => 'Najd-Plateau-2026-b'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->postJson('/api/v1/auth/password/change', ['current_password' => $current, 'password' => 'Najd-Plateau-2026-a', 'password_confirmation' => 'Najd-Plateau-2026-a'])
        ->assertNoContent();
    expect($user->passwordHistories()->count())->toBe(5);
});

it('checks reset and changed passwords against the local breached list without any network call', function (): void {
    Http::fake(static fn (): never => throw new RuntimeException('Outbound HTTP is forbidden.'));
    markPasswordBreached('Riyadh-Breached-2026');
    $user = entityUser();
    $payload = ['password' => 'Riyadh-Breached-2026', 'password_confirmation' => 'Riyadh-Breached-2026'];

    $this->postJson('/api/v1/auth/password/reset', ['token' => 'any', 'email' => $user->email, ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
    signIn($user)->postJson('/api/v1/auth/password/change', ['current_password' => defaultPassword(), ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(Hash::check(defaultPassword(), (string) $user->refresh()->password))->toBeTrue();
    Http::assertNothingSent();
});

it('requires the correct current password to change it', function (): void {
    signIn(entityUser());

    $this->postJson('/api/v1/auth/password/change', ['current_password' => 'Wrong-Password-2026!', 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
});

it('clears the forced password change flag once a new password is set', function (): void {
    $user = User::factory()->withRole(RoleKey::Entity)->create(['password_change_required' => true]);
    signIn($user);

    $this->getJson('/api/v1/me/preferences')->assertForbidden();
    $this->postJson('/api/v1/auth/password/change', ['current_password' => defaultPassword(), 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])->assertNoContent();
    $this->getJson('/api/v1/me/preferences')->assertOk();
});

it('rate limits reset requests per email', function (): void {
    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'limited@moashir.example.sa'])->assertOk();
    }

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'limited@moashir.example.sa'])->assertTooManyRequests();
});

final class UserFactoryPassword
{
    public const string VALUE = UserFactory::PASSWORD;
}
