<?php

declare(strict_types=1);

use App\Models\Identity\RegistrationEmailVerification;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const REGISTRATION_EMAIL = 'info@alamal.example.sa';

function requestRegistrationEmailCode(string $email = REGISTRATION_EMAIL): TestResponse
{
    return test()->postJson('/api/v1/auth/register/email-code', ['official_email' => $email]);
}

function verifyRegistrationEmailCode(string $challengeId, string $code): TestResponse
{
    return test()->postJson('/api/v1/auth/register/email-code/verify', ['challenge_id' => $challengeId, 'code' => $code]);
}

function registrationChallenge(string $email = REGISTRATION_EMAIL): string
{
    return (string) requestRegistrationEmailCode($email)->assertOk()->json('data.challenge_id');
}

beforeEach(function (): void {
    Notification::fake();
});

it('e-mails a code to the official address and answers with the challenge only', function (): void {
    $response = requestRegistrationEmailCode('Info@Alamal.example.sa')
        ->assertOk()
        ->assertJsonPath('data.channel', 'email')
        ->assertJsonPath('data.length', 6);

    expect(array_keys($response->json('data')))->toBe(['challenge_id', 'channel', 'length', 'expires_at', 'resend_available_at'])
        ->and(json_encode($response->json()))->not->toContain(otpCodeSentToAddress(REGISTRATION_EMAIL));
});

it('stores the address encrypted and the code hashed', function (): void {
    requestRegistrationEmailCode()->assertOk();

    $stored = RegistrationEmailVerification::query()->sole();
    $raw = (array) DB::table('registration_email_verifications')->sole();

    expect($stored->email)->toBe(REGISTRATION_EMAIL)
        ->and(json_encode($raw))->not->toContain(REGISTRATION_EMAIL)
        ->and($raw['code_hash'])->not->toBe(otpCodeSentToAddress(REGISTRATION_EMAIL));
});

it('answers an address that already belongs to an account exactly like a new one', function (): void {
    entityWithOwner()->owner?->forceFill(['email' => 'taken@alamal.example.sa'])->save();

    $new = requestRegistrationEmailCode()->assertOk()->json('data');
    $taken = requestRegistrationEmailCode('taken@alamal.example.sa')->assertOk()->json('data');

    expect(array_keys($taken))->toBe(array_keys($new))
        ->and($taken['channel'])->toBe($new['channel'])
        ->and($taken['length'])->toBe($new['length'])
        ->and(otpCodeSentToAddress('taken@alamal.example.sa'))->toHaveLength(6);
});

it('returns a single-use token for the right code, valid for 30 minutes', function (): void {
    $challengeId = registrationChallenge();

    $response = verifyRegistrationEmailCode($challengeId, otpCodeSentToAddress(REGISTRATION_EMAIL))->assertOk();
    $token = (string) $response->json('data.email_verification_token');
    $stored = RegistrationEmailVerification::query()->sole();

    expect(strlen($token))->toBe(64)
        ->and($stored->token_hash)->not->toBe($token)
        ->and($stored->token_expires_at?->diffInMinutes(now(), true))->toEqualWithDelta(30, 0.1)
        ->and($response->json('data.expires_at'))->toBe($stored->token_expires_at?->toIso8601String());

    $this->postJson('/api/v1/auth/register', registrationPayload(['email_verification_token' => $token]))->assertCreated();
});

it('refuses a wrong code, then voids the challenge after the attempt limit', function (): void {
    $challengeId = registrationChallenge();
    $wrong = wrongCodeFor(otpCodeSentToAddress(REGISTRATION_EMAIL));

    foreach (range(1, 4) as $attempt) {
        verifyRegistrationEmailCode($challengeId, $wrong)->assertUnprocessable()->assertJsonPath('code', 'otp.invalid');
    }

    verifyRegistrationEmailCode($challengeId, $wrong)->assertStatus(423)->assertJsonPath('code', 'otp.attempts_exceeded');
    verifyRegistrationEmailCode($challengeId, otpCodeSentToAddress(REGISTRATION_EMAIL))->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');

    expect(RegistrationEmailVerification::query()->sole()->token_hash)->toBeNull();
});

it('refuses an expired code', function (): void {
    $challengeId = registrationChallenge();

    $this->travel(6)->minutes();

    verifyRegistrationEmailCode($challengeId, otpCodeSentToAddress(REGISTRATION_EMAIL))->assertUnprocessable()->assertJsonPath('code', 'otp.expired');
});

it('refuses an unknown challenge and a code used twice', function (): void {
    verifyRegistrationEmailCode('0b5ae1b2-1c34-4d0f-9b52-1f3f3cf7d0aa', '123456')->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');

    $challengeId = registrationChallenge();
    $code = otpCodeSentToAddress(REGISTRATION_EMAIL);
    verifyRegistrationEmailCode($challengeId, $code)->assertOk();

    verifyRegistrationEmailCode($challengeId, $code)->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
});

it('validates the request bodies', function (): void {
    requestRegistrationEmailCode('info-at-alamal')->assertUnprocessable()->assertJsonValidationErrors(['official_email']);
    $this->postJson('/api/v1/auth/register/email-code', [])->assertUnprocessable()->assertJsonValidationErrors(['official_email']);
    verifyRegistrationEmailCode('not-a-uuid', '12')->assertUnprocessable()->assertJsonValidationErrors(['challenge_id', 'code']);
    $this->postJson('/api/v1/auth/register/email-code/resend', ['challenge_id' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors(['challenge_id']);
});

it('resends a new code after the cooldown and voids the previous one', function (): void {
    $challengeId = registrationChallenge();
    $firstCode = otpCodeSentToAddress(REGISTRATION_EMAIL);

    $this->postJson('/api/v1/auth/register/email-code/resend', ['challenge_id' => $challengeId])->assertTooManyRequests()->assertJsonPath('code', 'otp.cooldown');

    $this->travel(61)->seconds();
    $newChallengeId = (string) $this->postJson('/api/v1/auth/register/email-code/resend', ['challenge_id' => $challengeId])
        ->assertOk()
        ->assertJsonPath('data.channel', 'email')
        ->json('data.challenge_id');

    expect($newChallengeId)->not->toBe($challengeId);
    Notification::assertSentOnDemandTimes(OtpCodeNotification::class, 2);
    verifyRegistrationEmailCode($challengeId, $firstCode)->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
});

it('refuses to resend an unknown or already verified challenge', function (): void {
    $this->postJson('/api/v1/auth/register/email-code/resend', ['challenge_id' => '0b5ae1b2-1c34-4d0f-9b52-1f3f3cf7d0aa'])->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');

    $challengeId = registrationChallenge();
    verifyRegistrationEmailCode($challengeId, otpCodeSentToAddress(REGISTRATION_EMAIL))->assertOk();
    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/register/email-code/resend', ['challenge_id' => $challengeId])->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
});

it('limits the codes sent to one address', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"]);
        requestRegistrationEmailCode()->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9']);
    requestRegistrationEmailCode()->assertTooManyRequests()->assertJsonPath('code', 'otp.cooldown');
});

it('limits the code requests from one network address', function (): void {
    foreach (range(1, 10) as $attempt) {
        requestRegistrationEmailCode("info{$attempt}@alamal.example.sa")->assertOk();
    }

    requestRegistrationEmailCode('info11@alamal.example.sa')->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});

it('prunes e-mail checks a day after they expire', function (): void {
    registrationChallenge();

    $this->travel(2)->days();
    Artisan::call('moashir:prune-otp-codes');

    expect(RegistrationEmailVerification::query()->count())->toBe(0);
});
