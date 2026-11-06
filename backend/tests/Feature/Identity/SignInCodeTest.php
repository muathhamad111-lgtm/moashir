<?php

declare(strict_types=1);

use App\Enums\Identity\OtpPurpose;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use App\Services\Identity\SecretHasher;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function signInCodeChallengeFor(User $user): string
{
    return (string) requestSignInCode($user->email)->assertOk()->json('data.otp.challenge_id');
}

function decoyRegistrationChallenge(): string
{
    $existing = ownerOf(entityWithOwner());

    return (string) test()->postJson('/api/v1/auth/register', registrationPayload(['official_email' => $existing->email]))
        ->assertCreated()
        ->json('data.otp.challenge_id');
}

it('rejects reuse of a consumed code', function (): void {
    $owner = ownerOf(entityWithOwner());
    $challengeId = signInCodeChallengeFor($owner);
    $code = lastOtpCodeSentTo($owner);

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.challenge_invalid');
});

it('voids the challenge after five wrong codes while the account lockout allows more attempts', function (): void {
    config()->set('moashir.auth.lockout.max_attempts', 10);
    $owner = ownerOf(entityWithOwner());
    $challengeId = signInCodeChallengeFor($owner);
    $wrong = wrongCodeFor(lastOtpCodeSentTo($owner));

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $wrong])->assertJsonPath('code', 'otp.invalid');
    }

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $wrong])->assertStatus(423)->assertJsonPath('code', 'otp.attempts_exceeded');
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => lastOtpCodeSentTo($owner)])->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
});

it('gives a decoy registration challenge the same wrong-code behaviour as a real one', function (): void {
    $challengeId = decoyRegistrationChallenge();

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => '123456'])->assertJsonPath('code', 'otp.invalid');
    }

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => '123456'])->assertStatus(423);
});

it('applies the resend cooldown to decoy challenges', function (): void {
    $challengeId = decoyRegistrationChallenge();

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertTooManyRequests()->assertJsonPath('code', 'otp.cooldown');

    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertOk()->assertJsonPath('data.otp.channel', 'email');
    Notification::assertNothingSent();
});

it('rejects an expired code', function (): void {
    $owner = ownerOf(entityWithOwner());
    $challengeId = signInCodeChallengeFor($owner);

    $this->travel(6)->minutes();

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => lastOtpCodeSentTo($owner)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.expired');
});

it('rejects an unknown challenge id', function (): void {
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22', 'code' => '123456'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.challenge_invalid');
});

it('never answers a challenge of another purpose on the sign-in endpoints', function (): void {
    $owner = ownerOf(entityWithOwner());
    $other = OtpCode::query()->create([
        'challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e23',
        'user_id' => $owner->id,
        'channel' => 'email',
        'purpose' => 'email_change',
        'code_hash' => 'x',
        'destination_masked' => 'i***@example.sa',
        'expires_at' => now()->addMinutes(5),
        'last_sent_at' => now(),
    ]);

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $other->challenge_id, 'code' => '123456'])->assertJsonPath('code', 'otp.challenge_invalid');
    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $other->challenge_id])->assertJsonPath('code', 'otp.challenge_invalid');
});

it('stores the sign-in code with its own purpose so verifying it never confirms the e-mail', function (): void {
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['email_verified_at' => null])->save();
    $challengeId = signInCodeChallengeFor($owner);

    expect(OtpCode::query()->where('challenge_id', $challengeId)->value('purpose'))->toBe(OtpPurpose::SignInCode);

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => lastOtpCodeSentTo($owner)])->assertOk();

    expect($owner->refresh()->email_verified_at)->toBeNull();
});

it('refuses a sign-in code challenge that belongs to a staff account', function (): void {
    $staff = systemAdmin();
    $forged = OtpCode::query()->create([
        'challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e24',
        'user_id' => $staff->id,
        'channel' => 'email',
        'purpose' => 'sign_in_code',
        'code_hash' => app(SecretHasher::class)->hash('123456'),
        'destination_masked' => 'i***@example.sa',
        'expires_at' => now()->addMinutes(5),
        'last_sent_at' => now()->subMinutes(2),
    ]);

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $forged->challenge_id, 'code' => '123456'])->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $forged->challenge_id])->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
    $this->assertGuest('web');
    expect($forged->refresh()->consumed_at)->toBeNull();
});

it('validates the code payloads', function (string $uri, array $payload, string $field): void {
    $this->postJson($uri, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'verify with short code' => ['/api/v1/auth/otp/verify', ['challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22', 'code' => '123'], 'code'],
    'verify with letters' => ['/api/v1/auth/otp/verify', ['challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22', 'code' => 'abcdef'], 'code'],
    'verify without challenge' => ['/api/v1/auth/otp/verify', ['code' => '123456'], 'challenge_id'],
    'resend with malformed challenge' => ['/api/v1/auth/otp/resend', ['challenge_id' => 'abc'], 'challenge_id'],
]);
