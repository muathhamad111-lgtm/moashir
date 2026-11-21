<?php

declare(strict_types=1);

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\PasswordResetLink;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetLinkNotification;
use App\Services\Identity\PasswordResetTokens;
use Database\Factories\Identity\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

const RESET_PASSWORD = 'Wadi-Hanifa-Spring-2026';

function resetTokenSentTo(User $user): string
{
    $token = '';

    Notification::assertSentTo($user, PasswordResetLinkNotification::class, function (PasswordResetLinkNotification $notification) use ($user, &$token): bool {
        $token = basename((string) $notification->toMail($user)->actionUrl);

        return true;
    });

    return $token;
}

function forgottenPasswordToken(User $user): string
{
    test()->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();

    return resetTokenSentTo($user);
}

/**
 * @return array{token: string, password: string, password_confirmation: string}
 */
function resetPayload(string $token, string $password = RESET_PASSWORD): array
{
    return ['token' => $token, 'password' => $password, 'password_confirmation' => $password];
}

beforeEach(function (): void {
    Notification::fake();
});

it('resets the password from the link alone, without the e-mail, and ends every session', function (string $kind): void {
    $user = $kind === 'entity' ? entityUser() : staffUser(RoleKey::Assessor);
    DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $token = forgottenPasswordToken($user);

    $this->postJson('/api/v1/auth/password/reset', resetPayload($token))->assertOk();

    expect(Hash::check(RESET_PASSWORD, (string) $user->refresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(AuthenticationEvent::query()->where('user_id', $user->id)->where('type', AuthEventType::PasswordResetCompleted)->exists())->toBeTrue();
})->with(['entity', 'staff']);

it('stores only a keyed hash of the 64-character token', function (): void {
    $user = entityUser();
    $token = forgottenPasswordToken($user);
    $link = PasswordResetLink::query()->where('user_id', $user->id)->sole();

    expect(strlen($token))->toBe(64)
        ->and($link->token_hash)->not->toContain($token)
        ->and($link->token_hash)->toBe(hash_hmac('sha256', $token, config()->string('app.key')))
        ->and($link->toArray())->not->toHaveKey('token_hash');
});

it('lets a link be used once only', function (): void {
    $user = entityUser();
    $token = forgottenPasswordToken($user);
    $this->postJson('/api/v1/auth/password/reset', resetPayload($token))->assertOk();

    $this->postJson('/api/v1/auth/password/reset', resetPayload($token, 'Another-Riyadh-Season-2026'))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'token.invalid');
    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $token])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'token.invalid');
    expect(Hash::check(RESET_PASSWORD, (string) $user->refresh()->password))->toBeTrue();
});

it('rejects a link after it expires', function (): void {
    $user = entityUser();
    $token = forgottenPasswordToken($user);

    $this->travel(61)->minutes();

    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $token])->assertUnprocessable()->assertJsonPath('code', 'token.invalid');
    $this->postJson('/api/v1/auth/password/reset', resetPayload($token))->assertUnprocessable()->assertJsonPath('code', 'token.invalid');
    expect(Hash::check(defaultPassword(), (string) $user->refresh()->password))->toBeTrue();
});

it('rejects a forged or wrong token the same way', function (): void {
    $user = entityUser();
    $token = forgottenPasswordToken($user);

    foreach (['forged-token', strrev($token), str_repeat('a', 64)] as $wrong) {
        $this->postJson('/api/v1/auth/password/reset/check', ['token' => $wrong])->assertUnprocessable()->assertJsonPath('code', 'token.invalid');
        $this->postJson('/api/v1/auth/password/reset', resetPayload($wrong))->assertUnprocessable()->assertJsonPath('code', 'token.invalid');
    }

    expect(Hash::check(defaultPassword(), (string) $user->refresh()->password))->toBeTrue();
});

it('keeps only the latest link working', function (): void {
    $user = entityUser();
    $first = forgottenPasswordToken($user);
    $this->travel(2)->minutes();
    Notification::fake();
    $second = forgottenPasswordToken($user);

    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $first])->assertUnprocessable();
    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $second])->assertOk();
    expect(PasswordResetLink::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('checks a link without using, extending or consuming it', function (): void {
    $user = entityUser();
    $token = forgottenPasswordToken($user);
    $before = PasswordResetLink::query()->where('user_id', $user->id)->sole();

    $this->travel(10)->minutes();
    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $token])->assertOk()->assertJsonPath('data.usable', true);
    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $token])->assertOk();

    $after = PasswordResetLink::query()->where('user_id', $user->id)->sole();
    expect($after->expires_at->equalTo($before->expires_at))->toBeTrue()
        ->and($after->created_at->equalTo($before->created_at))->toBeTrue();
    $this->postJson('/api/v1/auth/password/reset', resetPayload($token))->assertOk();
});

it('answers about the token only and never names the account', function (): void {
    $user = entityUser();
    $token = forgottenPasswordToken($user);

    $valid = $this->postJson('/api/v1/auth/password/reset/check', ['token' => $token])->assertOk()->getContent();
    $invalid = $this->postJson('/api/v1/auth/password/reset/check', ['token' => 'unknown-token'])->getContent();

    expect($valid)->not->toContain($user->email)->not->toContain($user->name)
        ->and($invalid)->not->toContain($user->email);
});

it('ignores an e-mail an older client still sends and resets the token owner only', function (): void {
    $owner = entityUser();
    $other = entityUser();
    $token = forgottenPasswordToken($owner);

    $this->postJson('/api/v1/auth/password/reset', [...resetPayload($token), 'email' => $other->email])->assertOk();

    expect(Hash::check(RESET_PASSWORD, (string) $owner->refresh()->password))->toBeTrue()
        ->and(Hash::check(defaultPassword(), (string) $other->refresh()->password))->toBeTrue();
});

it('enforces the password policy against the link owner', function (string $password, string $message): void {
    markPasswordBreached('Riyadh-Breached-2026');
    $user = entityUser();
    $token = forgottenPasswordToken($user);

    $this->postJson('/api/v1/auth/password/reset', resetPayload($token, $password), ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', $message);
    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $token])->assertOk();
})->with([
    'shorter than twelve characters' => ['Short-2026', 'The password field must be at least 12 characters.'],
    'found in a breach list' => ['Riyadh-Breached-2026', 'This password has appeared in a known data breach. Please choose a different password.'],
    'the current password' => [UserFactory::PASSWORD, 'You cannot reuse any of your last five passwords.'],
]);

it('requires the token and the password confirmation', function (): void {
    $this->postJson('/api/v1/auth/password/reset', ['password' => RESET_PASSWORD, 'password_confirmation' => RESET_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
    $this->postJson('/api/v1/auth/password/reset', ['token' => 'any', 'password' => RESET_PASSWORD, 'password_confirmation' => 'different-value'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
    $this->postJson('/api/v1/auth/password/reset/check', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('resets a staff password from the link an administrator sent', function (): void {
    $assessor = staffUser(RoleKey::Assessor);
    signIn(systemAdmin())->postJson("/api/v1/users/{$assessor->id}/password/reset-link")->assertAccepted();
    $token = resetTokenSentTo($assessor);
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/auth/password/reset/check', ['token' => $token])->assertOk();
    $this->postJson('/api/v1/auth/password/reset', resetPayload($token))->assertOk();

    expect(Hash::check(RESET_PASSWORD, (string) $assessor->refresh()->password))->toBeTrue();
    $this->postJson('/api/v1/auth/password/reset', resetPayload($token))->assertUnprocessable()->assertJsonPath('code', 'token.invalid');
});

it('rate limits reset attempts per token', function (): void {
    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/auth/password/reset', resetPayload('guessed-token'))->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/password/reset', resetPayload('guessed-token'))->assertTooManyRequests();
});

it('rate limits link checks per address', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/auth/password/reset/check', ['token' => "guess-{$attempt}"])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/password/reset/check', ['token' => 'guess-11'])->assertTooManyRequests();
});

it('prunes expired links and keeps live ones', function (): void {
    $expired = entityUser();
    forgottenPasswordToken($expired);
    $this->travel(61)->minutes();
    $live = entityUser();
    forgottenPasswordToken($live);

    $this->artisan('moashir:prune-password-reset-links')->assertSuccessful();

    expect(PasswordResetLink::query()->pluck('user_id')->all())->toBe([$live->id]);
});

it('deletes the link with its user', function (): void {
    $user = entityUser();
    app(PasswordResetTokens::class)->issue($user);

    $user->forceDelete();

    expect(PasswordResetLink::query()->count())->toBe(0);
});
