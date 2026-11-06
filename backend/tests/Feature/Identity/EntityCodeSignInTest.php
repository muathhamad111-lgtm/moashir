<?php

declare(strict_types=1);

use App\Contracts\SmsGateway;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\SmsPermanentFailure;
use App\Models\Entities\Entity;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\AccountLockedNotification;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Identity\OtpIssueThrottle;
use App\Services\Integrations\IntegrationNotifier;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const CODE_SENT_AR = 'إذا كان ما أدخلته يطابق حساب جهة مسجّلًا، فقد أرسلنا إليك رمز الدخول.';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function codeAnswerShape(TestResponse $response): array
{
    $answer = $response->assertOk()->json();
    unset($answer['data']['otp']['challenge_id'], $answer['data']['otp']['expires_at'], $answer['data']['otp']['resend_available_at']);

    return $answer;
}

function lastIgnoredReason(): mixed
{
    return AuthenticationEvent::query()->where('type', AuthEventType::OtpRequestIgnored)->latest('id')->firstOrFail()->meta['reason'] ?? null;
}

function verifySignInCode(string $challengeId, string $code): TestResponse
{
    return test()->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code]);
}

it('sends a sign-in code to the official e-mail when an entity types its e-mail', function (): void {
    $owner = ownerOf(entityWithOwner());

    $response = requestSignInCode($owner->email)
        ->assertOk()
        ->assertJsonPath('data.step', 'otp_required')
        ->assertJsonPath('data.requires_otp', true)
        ->assertJsonPath('data.otp.channel', 'email')
        ->assertJsonPath('data.otp.length', 6)
        ->assertJsonPath('data.message', CODE_SENT_AR)
        ->assertJsonMissingPath('data.user');

    $challenge = OtpCode::query()->where('challenge_id', $response->json('data.otp.challenge_id'))->sole();
    expect($challenge->purpose)->toBe(OtpPurpose::SignInCode)
        ->and($challenge->channel)->toBe(OtpChannel::Email)
        ->and($challenge->expires_at->diffInSeconds($challenge->last_sent_at, true))->toBe(300.0)
        ->and(json_encode($response->json()))->not->toContain(lastOtpCodeSentTo($owner))
        ->and(json_encode($response->json()))->not->toContain($owner->email);
    Notification::assertSentTo($owner, OtpCodeNotification::class, static fn (OtpCodeNotification $sent, array $channels): bool => $channels === ['mail']);
    $this->assertGuest('web');
});

it('answers in English with the same generic message', function (): void {
    $owner = ownerOf(entityWithOwner());

    $this->postJson('/api/v1/auth/otp/request', ['identifier' => $owner->email], ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.message', 'If an entity account matches what you entered, a sign-in code has been sent.');
});

it('sends the code to the official e-mail for a unified national number', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7012345678']));
    $owner = ownerOf($entity);

    requestSignInCode('7012345678')->assertOk()->assertJsonPath('data.otp.channel', 'email');

    Notification::assertSentTo($owner, OtpCodeNotification::class, static fn (OtpCodeNotification $sent, array $channels): bool => $channels === ['mail']);
});

it('follows what was typed when choosing the channel', function (string $typed, array $allowed, string $expected): void {
    allowSignInCodeChannels(...$allowed);
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7012345678']));
    $owner = ownerOf($entity);
    $owner->forceFill(['phone' => '+966551234567'])->save();
    $owner->forceFill(['phone_verified_at' => now()])->save();

    requestSignInCode($typed === 'email' ? $owner->email : $typed)->assertOk()->assertJsonPath('data.otp.channel', $expected);

    Notification::assertSentTo($owner, OtpCodeNotification::class, static fn (OtpCodeNotification $sent, array $channels): bool => $channels === ($expected === 'sms' ? [SmsChannel::class] : ['mail']));
})->with([
    'mobile, SMS allowed' => ['0551234567', ['email', 'sms'], 'sms'],
    'mobile, e-mail only' => ['0551234567', ['email'], 'email'],
    'e-mail, SMS allowed' => ['email', ['email', 'sms'], 'email'],
    'unified number, SMS allowed' => ['7012345678', ['email', 'sms'], 'email'],
]);

it('sends the code by e-mail when the SMS gateway is not available', function (): void {
    allowSignInCodeChannels('email', 'sms');
    $owner = verifiedMobileOwner();
    useSettingsSmsGateway();

    requestSignInCode('0551234567')->assertOk()->assertJsonPath('data.otp.channel', 'email');

    Notification::assertSentTo($owner, OtpCodeNotification::class, static fn (OtpCodeNotification $sent, array $channels): bool => $channels === ['mail']);
});

it('moves sign-in codes to e-mail once the hourly SMS cap is reached, and decoys never use it up', function (): void {
    config()->set('moashir.auth.otp.sign_in_sms_per_hour', 1);
    allowSignInCodeChannels('email', 'sms');
    $owner = verifiedMobileOwner();

    requestSignInCode('0559000001')->assertJsonPath('data.otp.channel', 'sms');
    requestSignInCode('0551234567')->assertJsonPath('data.otp.channel', 'sms');
    requestSignInCode('0551234567')->assertJsonPath('data.otp.channel', 'email');
    requestSignInCode('0559000001')->assertJsonPath('data.otp.channel', 'email');

    $this->travel(61)->minutes();

    requestSignInCode('0551234567')->assertJsonPath('data.otp.channel', 'sms');
    Notification::assertSentToTimes($owner, OtpCodeNotification::class, 3);
});

it('answers every account that cannot use a code exactly like an active entity and sends nothing', function (Closure $identifierOf, string $reason): void {
    $reference = codeAnswerShape(requestSignInCode(ownerOf(entityWithOwner())->email));
    Notification::fake();

    $answer = codeAnswerShape(requestSignInCode($identifierOf()));

    expect($answer)->toBe($reference)
        ->and(lastIgnoredReason())->toBe($reason);
    Notification::assertNothingSent();
})->with([
    'unknown e-mail' => [fn (): string => 'nobody@moashir.example.sa', 'unknown'],
    'unknown unified number' => [fn (): string => '7999999999', 'unknown'],
    'staff' => [fn (): string => systemAdmin()->email, 'not_entity'],
    'staff without an authenticator' => [fn (): string => User::factory()->withRole(RoleKey::Assessor)->create()->email, 'not_entity'],
    'Super Admin' => [fn (): string => superAdmin()->email, 'not_entity'],
    'deactivated entity' => [fn (): string => ownerOf(entityWithOwner(static fn ($factory) => $factory->deactivated()))->email, 'inactive'],
    'suspended account' => [fn (): string => tap(ownerOf(entityWithOwner()), fn (User $owner) => $owner->forceFill(['status' => 'suspended'])->save())->email, 'inactive'],
    'deleted entity' => [fn (): string => tap(ownerOf(entityWithOwner()), fn (User $owner) => Entity::query()->findOrFail($owner->entity_id)->delete())->email, 'inactive'],
    'locked account' => [fn (): string => tap(ownerOf(entityWithOwner()), fn (User $owner) => $owner->forceFill(['locked_until' => now()->addMinutes(15)])->save())->email, 'locked'],
    'per-account limit reached' => [fn (): string => tap(ownerOf(entityWithOwner()), fn (User $owner) => collect(range(1, 5))->each(fn () => app(OtpIssueThrottle::class)->hit($owner)))->email, 'throttled'],
]);

it('stores a keyed hash and never the identifier of an unknown account that asked for a code', function (): void {
    requestSignInCode('0550000001')->assertOk();

    $event = AuthenticationEvent::query()->where('type', AuthEventType::OtpRequestIgnored)->sole();
    expect($event->user_id)->toBeNull()
        ->and($event->identifier_type)->toBe('mobile')
        ->and($event->identifier_hash)->toHaveLength(64)
        ->and($event->meta)->toBe(['reason' => 'unknown'])
        ->and(json_encode($event->toArray()))->not->toContain('550000001');
});

it('gives a decoy the channel a real account would get, on request and on resend', function (): void {
    allowSignInCodeChannels('email', 'sms');

    $challengeId = requestSignInCode('0559000002')->assertOk()->assertJsonPath('data.otp.channel', 'sms')->json('data.otp.challenge_id');

    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertOk()->assertJsonPath('data.otp.channel', 'sms');
    Notification::assertNothingSent();
});

it('sends a pending entity its registration code by e-mail and signs it in once verified', function (): void {
    allowSignInCodeChannels('email', 'sms');
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    $owner = ownerOf($entity);

    $challengeId = requestSignInCode($owner->email)->assertOk()->assertJsonPath('data.otp.channel', 'email')->json('data.otp.challenge_id');

    expect(OtpCode::query()->where('challenge_id', $challengeId)->value('purpose'))->toBe(OtpPurpose::Login);

    verifySignInCode($challengeId, lastOtpCodeSentTo($owner))->assertOk()->assertJsonPath('data.step', 'authenticated');

    $this->assertAuthenticatedAs($owner, 'web');
    expect($entity->refresh()->isPendingVerification())->toBeFalse()
        ->and($owner->refresh()->email_verified_at)->not->toBeNull();
});

it('signs the entity in with the code, records the method and channel, rotates the session and resets the counter', function (): void {
    allowSignInCodeChannels('email', 'sms');
    $owner = verifiedMobileOwner();
    $owner->forceFill(['failed_login_attempts' => 3])->save();
    $verifiedAt = $owner->email_verified_at?->toIso8601String();
    $challengeId = requestSignInCode('0551234567')->json('data.otp.challenge_id');
    $sessionCookie = config()->string('session.cookie');
    $knownSessionId = str_repeat('a', 40);

    $response = $this->withCredentials()->withCookie($sessionCookie, $knownSessionId)
        ->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => smsCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.id', $owner->id);

    $this->assertAuthenticatedAs($owner, 'web');
    expect($response->getCookie($sessionCookie)?->getValue())->not->toBe($knownSessionId)
        ->and(AuthenticationEvent::query()->where('user_id', $owner->id)->where('type', AuthEventType::LoginSucceeded)->sole()->meta)->toBe(['method' => 'sign_in_code', 'channel' => 'sms'])
        ->and($owner->refresh()->failed_login_attempts)->toBe(0)
        ->and($owner->email_verified_at?->toIso8601String())->toBe($verifiedAt);
});

it('keeps the session id when a wrong code is entered', function (): void {
    $owner = ownerOf(entityWithOwner());
    $challengeId = requestSignInCode($owner->email)->json('data.otp.challenge_id');
    $sessionCookie = config()->string('session.cookie');
    $knownSessionId = str_repeat('b', 40);

    $response = $this->withCredentials()->withCookie($sessionCookie, $knownSessionId)
        ->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => wrongCodeFor(lastOtpCodeSentTo($owner))])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp.invalid');

    expect($response->getCookie($sessionCookie)?->getValue())->toBe($knownSessionId);
    $this->assertGuest('web');
});

it('counts wrong codes and wrong passwords toward one lockout, then refuses codes, verification and passwords', function (): void {
    $administrator = systemAdmin();
    $owner = ownerOf(entityWithOwner());

    signInWith($owner->email, 'Wrong-Password-2026!')->assertJsonPath('code', 'credentials.invalid');
    signInWith($owner->email, 'Wrong-Password-2026!')->assertJsonPath('code', 'credentials.invalid');

    $challengeId = requestSignInCode($owner->email)->json('data.otp.challenge_id');
    $code = lastOtpCodeSentTo($owner);
    verifySignInCode($challengeId, wrongCodeFor($code))->assertJsonPath('code', 'otp.invalid');
    verifySignInCode($challengeId, wrongCodeFor($code))->assertJsonPath('code', 'otp.invalid');
    verifySignInCode($challengeId, wrongCodeFor($code))
        ->assertStatus(423)
        ->assertJsonPath('code', 'account_locked')
        ->assertHeader('Retry-After', '900');

    expect($owner->refresh()->isLocked())->toBeTrue();
    Notification::assertSentTo($administrator, AccountLockedNotification::class);
    Notification::assertSentTo($owner, AccountLockedNotification::class);

    verifySignInCode($challengeId, $code)->assertStatus(423);
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertStatus(423);
    signInWith($owner->email, defaultPassword())->assertStatus(423);

    Notification::fake();
    requestSignInCode($owner->email)->assertOk()->assertJsonPath('data.step', 'otp_required');
    Notification::assertNothingSent();
    expect(lastIgnoredReason())->toBe('locked');
});

it('resends a sign-in code on the same channel after the cooldown and voids the previous one', function (): void {
    allowSignInCodeChannels('email', 'sms');
    $owner = verifiedMobileOwner();
    $challengeId = requestSignInCode('0551234567')->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertTooManyRequests()->assertJsonPath('code', 'otp.cooldown');

    $this->travel(61)->seconds();

    $newChallenge = $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])
        ->assertOk()
        ->assertJsonPath('data.otp.channel', 'sms')
        ->json('data.otp.challenge_id');

    expect(OtpCode::query()->where('challenge_id', $challengeId)->value('voided_at'))->not->toBeNull()
        ->and(OtpCode::query()->where('challenge_id', $newChallenge)->value('purpose'))->toBe(OtpPurpose::SignInCode);
    verifySignInCode($newChallenge, smsCodeSentTo($owner))->assertOk()->assertJsonPath('data.step', 'authenticated');
});

it('resends by e-mail once the mobile number is no longer verified', function (): void {
    allowSignInCodeChannels('email', 'sms');
    $owner = verifiedMobileOwner();
    $challengeId = requestSignInCode('0551234567')->assertJsonPath('data.otp.channel', 'sms')->json('data.otp.challenge_id');
    $owner->forceFill(['phone' => '+966559876543'])->save();
    Notification::fake();

    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/otp/resend', ['challenge_id' => $challengeId])->assertOk()->assertJsonPath('data.otp.channel', 'email');

    Notification::assertSentTo($owner, OtpCodeNotification::class, static fn (OtpCodeNotification $sent, array $channels): bool => $channels === ['mail']);
    Notification::assertNotSentTo($owner, OtpCodeNotification::class, static fn (OtpCodeNotification $sent, array $channels): bool => $channels === [SmsChannel::class]);
});

it('falls back to e-mail and raises INT-01 when the sign-in SMS fails for good', function (): void {
    allowSignInCodeChannels('email', 'sms');
    $administrator = systemAdmin();
    $owner = verifiedMobileOwner();
    requestSignInCode('0551234567')->assertJsonPath('data.otp.channel', 'sms');
    $sent = null;
    Notification::assertSentTo($owner, OtpCodeNotification::class, static function (OtpCodeNotification $notification, array $channels) use (&$sent): bool {
        $sent = $notification;

        return $channels === [SmsChannel::class];
    });
    $failingGateway = new class implements SmsGateway
    {
        public function send(SmsMessage $message): SmsSendResult
        {
            throw new SmsPermanentFailure(ProviderErrorKind::Misconfigured, IntegrationProvider::Taqnyat);
        }
    };

    expect($sent)->toBeInstanceOf(OtpCodeNotification::class);
    new SmsChannel($failingGateway, app(IntegrationNotifier::class))->send($owner, $sent);

    Notification::assertSentTo($owner, OtpCodeNotification::class, static fn (OtpCodeNotification $notification, array $channels): bool => $channels === ['mail']);
    Notification::assertSentTo($administrator, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::SmsSendFailed);
});

it('never sends a sign-in SMS to a mobile number that was not verified', function (): void {
    allowSignInCodeChannels('email', 'sms');
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['phone' => '+966551234567'])->save();

    requestSignInCode('0551234567')->assertOk()->assertJsonPath('data.otp.channel', 'sms');

    Notification::assertNothingSent();
    expect(lastIgnoredReason())->toBe('unknown');
});

it('rate limits code requests per identifier across addresses', function (): void {
    foreach (range(1, 3) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.'.$attempt])->postJson('/api/v1/auth/otp/request', ['identifier' => 'nobody@moashir.example.sa'])->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.2.1.1'])->postJson('/api/v1/auth/otp/request', ['identifier' => 'Nobody@Moashir.example.sa'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited')
        ->assertHeader('Retry-After');
});

it('rate limits code requests per address across identifiers', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.1'])->postJson('/api/v1/auth/otp/request', ['identifier' => 'nobody'.$attempt.'@moashir.example.sa'])->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.1'])->postJson('/api/v1/auth/otp/request', ['identifier' => 'nobody11@moashir.example.sa'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});

it('validates the identifier with the sign-in message', function (array $payload): void {
    $loginMessage = signInWith('0114567890', 'x')->json('errors.identifier');

    $this->postJson('/api/v1/auth/otp/request', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('identifier');

    if (($payload['identifier'] ?? null) === '0114567890') {
        expect($this->postJson('/api/v1/auth/otp/request', $payload)->json('errors.identifier'))->toBe($loginMessage);
    }

    Notification::assertNothingSent();
})->with([
    'missing identifier' => [[]],
    'landline number' => [['identifier' => '0114567890']],
    'malformed e-mail' => [['identifier' => 'not-an-email@']],
    'Arabic-Indic digits' => [['identifier' => '٧٠٠١٢٣٤٥٦٧']],
    'identifier too long' => [['identifier' => str_repeat('a', 250).'@example.sa']],
    'legacy e-mail field only' => [['email' => 'person@moashir.example.sa']],
]);
