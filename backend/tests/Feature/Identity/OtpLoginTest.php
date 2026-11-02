<?php

declare(strict_types=1);

use App\Contracts\SmsGateway;
use App\Enums\Identity\RoleKey;
use App\Integrations\Fakes\FakeSmsGateway;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

function sentOtpCode(User $user): string
{
    $code = null;

    Notification::assertSentTo($user, OtpCodeNotification::class, function (OtpCodeNotification $notification) use ($user, &$code): bool {
        $code = $notification->toMail($user)->introLines[1] ?? null;

        return true;
    });

    return (string) $code;
}

function requestOtp(string $email, string $channel = 'email'): TestResponse
{
    return test()->postJson('/api/v1/auth/otp/request', ['email' => $email, 'channel' => $channel]);
}

beforeEach(function (): void {
    Notification::fake();
});

it('sends a six-digit code by email and returns a challenge without the code', function (): void {
    $user = entityUser();

    $response = requestOtp($user->email)
        ->assertOk()
        ->assertJsonPath('data.step', 'otp_required')
        ->assertJsonPath('data.otp.channel', 'email')
        ->assertJsonPath('data.otp.length', 6)
        ->assertJsonStructure(['data' => ['otp' => ['challenge_id', 'expires_at', 'resend_available_at'], 'message']]);

    expect(sentOtpCode($user))->toMatch('/^\d{6}$/')
        ->and(json_encode($response->json()))->not->toContain(sentOtpCode($user));
});

it('answers an unknown email exactly like a known one without sending anything', function (): void {
    $known = requestOtp(entityUser()->email)->json('data');
    $unknown = requestOtp('nobody@moashir.example.sa')->assertOk()->json('data');

    expect(array_keys($unknown))->toBe(array_keys($known))
        ->and(array_keys($unknown['otp']))->toBe(array_keys($known['otp']))
        ->and($unknown['message'])->toBe($known['message']);
    Notification::assertSentTimes(OtpCodeNotification::class, 1);
});

it('signs the user in with the correct code and consumes it', function (): void {
    $user = entityUser();
    $challengeId = requestOtp($user->email)->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => sentOtpCode($user)])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.id', $user->id);

    expect(OtpCode::query()->where('challenge_id', $challengeId)->value('consumed_at'))->not->toBeNull();
});

it('rejects reuse of a consumed code', function (): void {
    $user = entityUser();
    $challengeId = requestOtp($user->email)->json('data.otp.challenge_id');
    $code = sentOtpCode($user);

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.challenge_invalid');
});

it('voids the challenge after five wrong codes', function (): void {
    $user = entityUser();
    $challengeId = requestOtp($user->email)->json('data.otp.challenge_id');
    $wrong = sentOtpCode($user) === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $wrong])->assertJsonPath('code', 'otp.invalid');
    }

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $wrong])->assertStatus(423)->assertJsonPath('code', 'otp.attempts_exceeded');
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => sentOtpCode($user)])->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
});

it('gives a decoy challenge the same wrong-code behaviour as a real one', function (): void {
    $challengeId = requestOtp('nobody@moashir.example.sa')->json('data.otp.challenge_id');

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => '123456'])->assertJsonPath('code', 'otp.invalid');
    }

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => '123456'])->assertStatus(423);
});

it('rejects an expired code', function (): void {
    $user = entityUser();
    $challengeId = requestOtp($user->email)->json('data.otp.challenge_id');

    $this->travel(6)->minutes();

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => sentOtpCode($user)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.expired');
});

it('rejects an unknown challenge id', function (): void {
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22', 'code' => '123456'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.challenge_invalid');
});

it('enforces the resend cooldown and voids the previous code on resend', function (): void {
    $user = entityUser();
    $challengeId = requestOtp($user->email)->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'otp.cooldown')
        ->assertHeader('Retry-After');

    $this->travel(61)->seconds();

    $newChallenge = $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertOk()->json('data.otp.challenge_id');

    expect($newChallenge)->not->toBe($challengeId)
        ->and(OtpCode::query()->where('challenge_id', $challengeId)->value('voided_at'))->not->toBeNull();
});

it('applies the same resend cooldown to decoy challenges', function (): void {
    $challengeId = requestOtp('nobody@moashir.example.sa')->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertTooManyRequests()->assertJsonPath('code', 'otp.cooldown');
});

it('sends the code through the SMS gateway when the SMS channel is requested', function (): void {
    $user = User::factory()->withRole(RoleKey::Entity)->create(['phone' => '+966512345678']);

    requestOtp($user->email, 'sms')->assertOk()->assertJsonPath('data.otp.channel', 'sms');

    Notification::assertSentTo($user, OtpCodeNotification::class, static fn ($notification, array $channels): bool => $channels === [SmsChannel::class]);
});

it('falls back to email when the user has no mobile number', function (): void {
    $user = entityUser();

    requestOtp($user->email, 'sms')->assertOk()->assertJsonPath('data.otp.channel', 'email');
});

it('delivers the SMS body through the gateway contract', function (): void {
    Notification::swap(new ChannelManager(app()));
    $user = User::factory()->withRole(RoleKey::Entity)->create(['phone' => '+966512345678']);

    requestOtp($user->email, 'sms')->assertOk();

    $gateway = app(SmsGateway::class);
    expect($gateway)->toBeInstanceOf(FakeSmsGateway::class)
        ->and($gateway->sent())->toHaveCount(1)
        ->and($gateway->sent()[0]['recipient'])->toBe('+966512345678')
        ->and($gateway->sent()[0]['message'])->toMatch('/\d{6}/');
});

it('rejects a disabled SMS channel', function (): void {
    config()->set('moashir.auth.login_modes.sms_otp', false);

    requestOtp(entityUser()->email, 'sms')->assertUnprocessable()->assertJsonPath('code', 'login.mode_disabled');
});

it('validates the OTP payloads', function (string $uri, array $payload, string $field): void {
    $this->postJson($uri, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'request without email' => ['/api/v1/auth/otp/request', [], 'email'],
    'request with unknown channel' => ['/api/v1/auth/otp/request', ['email' => 'person@moashir.example.sa', 'channel' => 'fax'], 'channel'],
    'verify with short code' => ['/api/v1/auth/otp/verify', ['challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22', 'code' => '123'], 'code'],
    'verify with letters' => ['/api/v1/auth/otp/verify', ['challenge_id' => '6f1c2b8e-1d3a-4c2e-9e5b-0a7d8c9b1e22', 'code' => 'abcdef'], 'code'],
    'verify without challenge' => ['/api/v1/auth/otp/verify', ['code' => '123456'], 'challenge_id'],
    'resend with malformed challenge' => ['/api/v1/auth/otp/resend', ['challenge_id' => 'abc'], 'challenge_id'],
]);

it('rate limits OTP requests to three per ten minutes per email', function (): void {
    foreach (range(1, 3) as $attempt) {
        requestOtp('limited@moashir.example.sa')->assertOk();
    }

    requestOtp('limited@moashir.example.sa')->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});

it('still requires the TOTP challenge for staff signing in by OTP', function (): void {
    $user = systemAdmin();
    $challengeId = requestOtp($user->email)->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => sentOtpCode($user)])
        ->assertOk()
        ->assertJsonPath('data.step', 'mfa_challenge');
    $this->assertGuest('web');
});
