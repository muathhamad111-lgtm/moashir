<?php

declare(strict_types=1);

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\OtpPurpose;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountLockedNotification;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function entityWithTotp(): User
{
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(totpAttributes())->save();

    return $owner->refresh();
}

it('asks an entity for a one-time e-mail code after the password while the setting is on by default', function (): void {
    $owner = ownerOf(entityWithOwner());

    $response = signInWith($owner->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'otp_required')
        ->assertJsonPath('data.otp.channel', 'email')
        ->assertJsonPath('data.otp.length', 6)
        ->assertJsonMissingPath('data.user');

    $this->assertGuest('web');
    $challenge = OtpCode::query()->where('challenge_id', $response->json('data.otp.challenge_id'))->sole();
    expect($challenge->purpose)->toBe(OtpPurpose::LoginSecondFactor)
        ->and($challenge->expires_at->diffInSeconds($challenge->last_sent_at, true))->toBe(300.0)
        ->and(json_encode($response->json()))->not->toContain(lastOtpCodeSentTo($owner));
});

it('completes the entity sign-in with the e-mail code', function (): void {
    $owner = ownerOf(entityWithOwner());
    $challengeId = signInWith($owner->email, defaultPassword())->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => lastOtpCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.id', $owner->id);

    $this->assertAuthenticatedAs($owner, 'web');
    expect(AuthenticationEvent::query()->where('user_id', $owner->id)->where('type', AuthEventType::LoginSucceeded)->sole()->meta)
        ->toBe(['method' => 'email_second_factor']);
});

it('signs an entity in after the password alone while the setting is off', function (): void {
    setEntitySecondFactor(false);
    $owner = ownerOf(entityWithOwner());

    signInWith($owner->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'authenticated');

    Notification::assertNotSentTo($owner, OtpCodeNotification::class);
});

it('always challenges an entity that enrolled an authenticator, whatever the setting', function (bool $enabled): void {
    setEntitySecondFactor($enabled);
    $owner = entityWithTotp();

    signInWith($owner->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'mfa_challenge')
        ->assertJsonMissingPath('data.user');

    $this->assertGuest('web');
    Notification::assertNotSentTo($owner, OtpCodeNotification::class);

    $this->postJson('/api/v1/auth/mfa/challenge', ['code' => currentTotp()])->assertOk()->assertJsonPath('data.user.id', $owner->id);
})->with(['setting on' => [true], 'setting off' => [false]]);

it('keeps mandatory TOTP for staff and never sends them an e-mail code', function (bool $enabled): void {
    setEntitySecondFactor($enabled);
    $enrolled = systemAdmin();
    $notEnrolled = User::factory()->withRole(RoleKey::Assessor)->create(['mfa_required' => true]);

    signInWith($enrolled->email, defaultPassword())->assertJsonPath('data.step', 'mfa_challenge');
    signInWith($notEnrolled->email, defaultPassword())->assertJsonPath('data.step', 'mfa_enrollment');

    Notification::assertNothingSentTo($enrolled);
    Notification::assertNothingSentTo($notEnrolled);
})->with(['setting on' => [true], 'setting off' => [false]]);

it('refuses to complete a second-factor challenge from another browser session', function (): void {
    $owner = ownerOf(entityWithOwner());
    $challengeId = signInWith($owner->email, defaultPassword())->json('data.otp.challenge_id');
    $code = lastOtpCodeSentTo($owner);

    $this->flushSession();

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.challenge_invalid');
    $this->assertGuest('web');
});

it('counts wrong e-mail codes toward the account lockout across challenges', function (): void {
    $administrator = systemAdmin();
    $owner = ownerOf(entityWithOwner());

    foreach (range(1, 2) as $round) {
        $challengeId = signInWith($owner->email, defaultPassword())->json('data.otp.challenge_id');
        $wrong = wrongCodeFor(lastOtpCodeSentTo($owner));
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $wrong])->assertJsonPath('code', 'otp.invalid');
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $wrong])->assertJsonPath('code', 'otp.invalid');
    }

    $challengeId = signInWith($owner->email, defaultPassword())->json('data.otp.challenge_id');
    $code = lastOtpCodeSentTo($owner);

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => wrongCodeFor($code)])
        ->assertStatus(423)
        ->assertJsonPath('code', 'account_locked')
        ->assertHeader('Retry-After', '900');

    expect($owner->refresh()->isLocked())->toBeTrue();
    Notification::assertSentTo($administrator, AccountLockedNotification::class);
    Notification::assertSentTo($owner, AccountLockedNotification::class);
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code])->assertStatus(423);
    signInWith($owner->email, defaultPassword())->assertStatus(423);
});

it('throttles e-mail codes per account, not only per address', function (): void {
    $owner = ownerOf(entityWithOwner());

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.'.$attempt])->postJson('/api/v1/auth/login', ['identifier' => $owner->email, 'password' => defaultPassword()])
            ->assertJsonPath('data.step', 'otp_required');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->postJson('/api/v1/auth/login', ['identifier' => $owner->email, 'password' => defaultPassword()])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'otp.cooldown')
        ->assertHeader('Retry-After');
    Notification::assertSentToTimes($owner, OtpCodeNotification::class, 5);
});

it('resends a second-factor code after the cooldown and voids the previous one', function (): void {
    $owner = ownerOf(entityWithOwner());
    $challengeId = signInWith($owner->email, defaultPassword())->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertTooManyRequests()->assertJsonPath('code', 'otp.cooldown');

    $this->travel(61)->seconds();

    $newChallenge = $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertOk()->json('data.otp.challenge_id');

    expect(OtpCode::query()->where('challenge_id', $challengeId)->value('voided_at'))->not->toBeNull();
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $newChallenge, 'code' => lastOtpCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated');
});

it('asks for a fresh password sign-in once the second-factor code expired', function (): void {
    $owner = ownerOf(entityWithOwner());
    $challengeId = signInWith($owner->email, defaultPassword())->json('data.otp.challenge_id');

    $this->travel(11)->minutes();

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => lastOtpCodeSentTo($owner)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.challenge_invalid');
});
