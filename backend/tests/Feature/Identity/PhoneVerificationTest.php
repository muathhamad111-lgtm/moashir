<?php

declare(strict_types=1);

use App\Contracts\SmsGateway;
use App\Enums\Identity\OtpPurpose;
use App\Integrations\Fakes\FakeSmsGateway;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

function ownerWithMobile(string $phone = '+966551234567'): User
{
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['phone' => $phone])->save();

    return $owner->refresh();
}

it('sends a code by SMS to the stored mobile number and verifies it', function (): void {
    Notification::fake();
    $owner = ownerWithMobile();
    signIn($owner);

    $challengeId = $this->postJson('/api/v1/me/phone/verification')
        ->assertOk()
        ->assertJsonPath('data.channel', 'sms')
        ->json('data.challenge_id');

    expect(OtpCode::query()->where('challenge_id', $challengeId)->value('purpose'))->toBe(OtpPurpose::VerifyPhone);

    $this->postJson('/api/v1/me/phone/verification/confirm', ['challenge_id' => $challengeId, 'code' => smsCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.phone_verified', true);

    expect($owner->refresh()->phone_verified_at)->not->toBeNull();
    $this->getJson('/api/v1/entities/me')->assertJsonPath('data.phone_verified', true);

    $this->flushSession();
    app('auth')->forgetGuards();
    signInWith('0551234567', defaultPassword())->assertOk()->assertJsonPath('data.user.id', $owner->id);
});

it('delivers the verification code through the SMS gateway contract', function (): void {
    Notification::swap(new ChannelManager(app()));
    signIn(ownerWithMobile());

    $this->postJson('/api/v1/me/phone/verification')->assertOk();

    $gateway = app(SmsGateway::class);
    expect($gateway)->toBeInstanceOf(FakeSmsGateway::class)
        ->and($gateway->sent())->toHaveCount(1)
        ->and($gateway->sent()[0]['recipient'])->toBe('+966551234567')
        ->and($gateway->sent()[0]['message'])->toMatch('/\d{6}/');
});

it('refuses a wrong code and a code of another account', function (): void {
    Notification::fake();
    $owner = ownerWithMobile();
    $other = ownerWithMobile('+966559998877');
    $otherChallenge = signIn($other)->postJson('/api/v1/me/phone/verification')->json('data.challenge_id');

    $this->flushSession();
    app('auth')->forgetGuards();
    $challengeId = signIn($owner)->postJson('/api/v1/me/phone/verification')->json('data.challenge_id');

    $this->postJson('/api/v1/me/phone/verification/confirm', ['challenge_id' => $challengeId, 'code' => wrongCodeFor(smsCodeSentTo($owner))])
        ->assertUnprocessable()->assertJsonPath('code', 'otp.invalid');
    $this->postJson('/api/v1/me/phone/verification/confirm', ['challenge_id' => $otherChallenge, 'code' => smsCodeSentTo($other)])
        ->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');

    expect($owner->refresh()->phone_verified_at)->toBeNull()
        ->and($other->refresh()->phone_verified_at)->toBeNull();
});

it('refuses to verify a number already verified on another account', function (): void {
    Notification::fake();
    $holder = ownerWithMobile();
    $holder->forceFill(['phone_verified_at' => Date::now()])->save();
    $owner = ownerWithMobile();
    signIn($owner);

    $challengeId = $this->postJson('/api/v1/me/phone/verification')->json('data.challenge_id');

    $this->postJson('/api/v1/me/phone/verification/confirm', ['challenge_id' => $challengeId, 'code' => smsCodeSentTo($owner)])
        ->assertConflict()
        ->assertJsonPath('code', 'phone.in_use');
    expect($owner->refresh()->phone_verified_at)->toBeNull();
});

it('refuses a code sent to a number that changed in the meantime', function (): void {
    Notification::fake();
    $owner = ownerWithMobile();
    signIn($owner);
    $challengeId = $this->postJson('/api/v1/me/phone/verification')->json('data.challenge_id');
    $code = smsCodeSentTo($owner);

    $owner->forceFill(['phone' => '+966557776655'])->save();

    $this->postJson('/api/v1/me/phone/verification/confirm', ['challenge_id' => $challengeId, 'code' => $code])
        ->assertUnprocessable()->assertJsonPath('code', 'otp.challenge_invalid');
});

it('explains why a verification cannot start', function (?string $phone, bool $verified, int $status, string $code): void {
    Notification::fake();
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['phone' => $phone])->save();
    $owner->forceFill(['phone_verified_at' => $verified ? Date::now() : null])->save();

    signIn($owner)->postJson('/api/v1/me/phone/verification')->assertStatus($status)->assertJsonPath('code', $code);
})->with([
    'no number' => [null, false, 409, 'phone.missing'],
    'already verified' => ['+966551234567', true, 409, 'phone.already_verified'],
]);

it('asks guests to sign in and validates the confirmation payload', function (): void {
    $this->postJson('/api/v1/me/phone/verification')->assertUnauthorized();

    signIn(ownerWithMobile())->postJson('/api/v1/me/phone/verification/confirm', ['challenge_id' => 'abc', 'code' => '12'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['challenge_id', 'code']);
});
