<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Identity\OtpCode;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * @param  array<string, mixed>  $payload
 */
function register(array $payload): TestResponse
{
    return test()->postJson('/api/v1/auth/register', $payload);
}

function registeredOwner(string $email = 'info@alamal.example.sa'): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

beforeEach(function (): void {
    Notification::fake();
});

it('registers an entity pending verification and sends an e-mail code without revealing it', function (): void {
    $response = register(registrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.step', 'otp_required')
        ->assertJsonPath('data.requires_otp', true)
        ->assertJsonPath('data.otp.channel', 'email')
        ->assertJsonPath('data.otp.length', 6)
        ->assertJsonStructure(['data' => ['otp' => ['challenge_id', 'expires_at', 'resend_available_at'], 'message'], 'meta', 'errors']);

    $owner = registeredOwner();
    $entity = Entity::query()->findOrFail($owner->entity_id);

    expect($entity->status)->toBe(EntityStatus::PendingVerification)
        ->and($entity->name->ar)->toBe('جمعية الأمل الخيرية')
        ->and($entity->governance_grade)->toBe('87.45')
        ->and($entity->hasCompleteProfile())->toBeTrue()
        ->and($owner->roleKeys())->toBe([RoleKey::Entity])
        ->and($owner->name)->toBe('سلمان بن فهد العتيبي')
        ->and($owner->phone)->toBe('+966551234567')
        ->and($owner->email_verified_at)->toBeNull()
        ->and($owner->mfa_required)->toBeFalse()
        ->and($owner->passwordHistories()->count())->toBe(1)
        ->and(json_encode($response->json()))->not->toContain(otpCodeSentTo($owner));
});

it('stores the terms acceptance with the version and the time of acceptance', function (): void {
    $privacy = publishTerms(TermsKind::Privacy);
    $payload = registrationPayload(['privacy_version_id' => $privacy->id]);

    register($payload)->assertCreated();

    $accepted = TermsAcceptance::query()->where('user_id', registeredOwner()->id)->pluck('terms_version_id')->sort()->values()->all();

    expect($accepted)->toBe(collect([$payload['terms_version_id'], $privacy->id])->sort()->values()->all());
});

it('records marketing consent only on an explicit opt-in', function (?bool $optIn, bool $recorded): void {
    $payload = registrationPayload();
    unset($payload['marketing_consent']);

    register($optIn === null ? $payload : [...$payload, 'marketing_consent' => $optIn])->assertCreated();

    expect(Entity::query()->firstOrFail()->marketing_consent_at !== null)->toBe($recorded);
})->with([
    'opted in' => [true, true],
    'opted out' => [false, false],
    'not answered' => [null, false],
]);

it('accepts an incomplete profile and leaves governance and counts for later', function (): void {
    register(registrationPayload(['governance_grade' => null, 'employees_count' => null, 'annual_beneficiaries_count' => null]))->assertCreated();

    expect(Entity::query()->firstOrFail()->profile_completed_at)->toBeNull();
});

it('notifies the entity and the system administrators of the registration', function (): void {
    $admin = systemAdmin();

    register(registrationPayload())->assertCreated();

    Notification::assertSentTo(registeredOwner(), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityRegistered);
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityRegistered);
    Notification::assertSentTo(registeredOwner(), OtpCodeNotification::class);
});

it('audits the registration without personal data', function (): void {
    register(registrationPayload())->assertCreated();

    $audit = Audit::query()->where('event', AuditEvent::EntityRegistered->value)->firstOrFail();
    $everyAudit = json_encode(Audit::query()->get(['old_values', 'new_values'])->toArray(), JSON_UNESCAPED_UNICODE);

    expect($everyAudit)->not->toContain('info@alamal.example.sa')
        ->not->toContain('سلمان')
        ->and($audit->new_values)->toHaveKeys(['status', 'entity_type', 'region_id', 'marketing_consent', 'terms_version_ids']);
});

it('activates the entity and signs it in when the e-mail code is verified', function (): void {
    $challengeId = register(registrationPayload(['employees_count' => null]))->json('data.otp.challenge_id');
    $owner = registeredOwner();

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => otpCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.entity.status', 'active')
        ->assertJsonPath('data.user.entity.profile_completed', false)
        ->assertJsonPath('data.user.entity.name.ar', 'جمعية الأمل الخيرية');

    $entity = Entity::query()->findOrFail($owner->entity_id);

    expect($entity->status)->toBe(EntityStatus::Active)
        ->and($entity->verified_at)->not->toBeNull()
        ->and($owner->refresh()->email_verified_at)->not->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::EntityVerified->value)->count())->toBe(1);
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileIncomplete);
});

it('does not ask a verified entity with a complete profile to complete it', function (): void {
    $challengeId = register(registrationPayload())->json('data.otp.challenge_id');
    $owner = registeredOwner();

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => otpCodeSentTo($owner)])->assertOk();

    Notification::assertNotSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileIncomplete);
});

it('returns the entity summary in the session of a signed-in entity', function (): void {
    $entity = entityWithOwner();
    signIn(ownerOf($entity));

    $this->getJson('/api/v1/auth/session')
        ->assertOk()
        ->assertJsonPath('data.entity_id', $entity->id)
        ->assertJsonPath('data.entity', [
            'id' => $entity->id,
            'reference' => $entity->reference(),
            'name' => $entity->name->toArray(),
            'status' => 'active',
            'profile_completed' => true,
        ]);
});

it('keeps the session entity summary empty for staff', function (): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.entity', null);
});

it('asks an unverified entity signing in with its password for an e-mail code', function (): void {
    register(registrationPayload())->assertCreated();
    $owner = registeredOwner();

    $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'mode' => 'password', 'password' => 'Wadi-Hanifa-Spring-2026'])
        ->assertOk()
        ->assertJsonPath('data.step', 'otp_required')
        ->assertJsonPath('data.requires_otp', true);

    $this->getJson('/api/v1/auth/session')->assertUnauthorized();
    expect(Entity::query()->findOrFail($owner->entity_id)->status)->toBe(EntityStatus::PendingVerification);
    Notification::assertSentToTimes($owner, OtpCodeNotification::class, 2);
});

it('answers an already registered e-mail exactly like a new one and creates nothing', function (): void {
    entityWithOwner()->owner?->forceFill(['email' => 'info@alamal.example.sa'])->save();
    $entities = Entity::query()->count();

    $known = register(registrationPayload())->assertCreated()->json('data');

    expect(array_keys($known))->toBe(['step', 'requires_otp', 'otp', 'message'])
        ->and(array_keys($known['otp']))->toBe(['challenge_id', 'channel', 'length', 'expires_at', 'resend_available_at'])
        ->and(Entity::query()->count())->toBe($entities)
        ->and(OtpCode::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('treats the official e-mail case-insensitively for duplicates', function (): void {
    entityWithOwner()->owner?->forceFill(['email' => 'info@alamal.example.sa'])->save();

    register(registrationPayload(['official_email' => 'INFO@ALAMAL.example.sa']))->assertCreated();

    expect(User::query()->whereRaw('lower(email) = ?', ['info@alamal.example.sa'])->count())->toBe(1);
});

it('rejects invalid registration data', function (Closure $mutate, string $field): void {
    register($mutate(registrationPayload()))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);

    expect(Entity::query()->count())->toBe(0);
})->with([
    'missing Arabic name' => [static fn (array $payload): array => [...$payload, 'name' => ['en' => 'Al-Amal']], 'name.ar'],
    'unknown entity type' => [static fn (array $payload): array => [...$payload, 'entity_type' => 'family_fund'], 'entity_type'],
    'superseded entity type' => [static fn (array $payload): array => [...$payload, 'entity_type' => 'association'], 'entity_type'],
    'unknown sector' => [static fn (array $payload): array => [...$payload, 'development_sector' => 'sports'], 'development_sector'],
    'unknown size' => [static fn (array $payload): array => [...$payload, 'org_size' => 'huge'], 'org_size'],
    'founding year in the future' => [static fn (array $payload): array => [...$payload, 'founding_year' => (int) date('Y') + 1], 'founding_year'],
    'founding year too old' => [static fn (array $payload): array => [...$payload, 'founding_year' => 1850], 'founding_year'],
    'missing region' => [static fn (array $payload): array => [...$payload, 'region_id' => null], 'region_id'],
    'missing city' => [static fn (array $payload): array => [...$payload, 'city_id' => null], 'city_id'],
    'city of another region' => [static fn (array $payload): array => [...$payload, 'city_id' => regionWithCity()->id], 'city_id'],
    'inactive city' => [static fn (array $payload): array => [...$payload, 'city_id' => City::factory()->inactive()->create(['region_id' => $payload['region_id']])->id], 'city_id'],
    'governance above 100' => [static fn (array $payload): array => [...$payload, 'governance_grade' => '100.01'], 'governance_grade'],
    'governance below 0' => [static fn (array $payload): array => [...$payload, 'governance_grade' => '-1'], 'governance_grade'],
    'governance with three decimals' => [static fn (array $payload): array => [...$payload, 'governance_grade' => '87.455'], 'governance_grade'],
    'governance as a band label' => [static fn (array $payload): array => [...$payload, 'governance_grade' => 'advanced'], 'governance_grade'],
    'negative employees' => [static fn (array $payload): array => [...$payload, 'employees_count' => -3], 'employees_count'],
    'strategic plan missing' => [static fn (array $payload): array => array_diff_key($payload, ['has_strategic_plan' => true]), 'has_strategic_plan'],
    'invalid e-mail' => [static fn (array $payload): array => [...$payload, 'official_email' => 'info-at-alamal'], 'official_email'],
    'invalid mobile' => [static fn (array $payload): array => [...$payload, 'phone' => '0123456789'], 'phone'],
    'short password' => [static fn (array $payload): array => [...$payload, 'password' => 'Short-2026', 'password_confirmation' => 'Short-2026'], 'password'],
    'password mismatch' => [static fn (array $payload): array => [...$payload, 'password_confirmation' => 'Another-Password-2026'], 'password'],
    'terms not accepted' => [static fn (array $payload): array => [...$payload, 'terms_accepted' => false], 'terms_accepted'],
    'stale terms version' => [static fn (array $payload): array => [...$payload, 'terms_version_id' => $payload['terms_version_id'] + 1000], 'terms_version_id'],
    'marketing consent not boolean' => [static fn (array $payload): array => [...$payload, 'marketing_consent' => 'maybe'], 'marketing_consent'],
]);

it('rejects a password found in the local breached-password list', function (): void {
    markPasswordBreached('Wadi-Hanifa-Spring-2026');

    register(registrationPayload())->assertUnprocessable()->assertJsonValidationErrors(['password']);
});

it('requires the privacy policy acceptance once a privacy policy is published', function (): void {
    publishTerms(TermsKind::Privacy);

    register(registrationPayload())->assertUnprocessable()->assertJsonValidationErrors(['privacy_version_id']);
});

it('refuses registration while no terms are published', function (): void {
    $payload = registrationPayload();
    TermsVersion::query()->update(['is_current' => false]);

    register($payload)->assertServiceUnavailable()->assertJsonPath('code', 'registration.terms_unavailable');
});

it('explains validation errors in the request language', function (): void {
    $this->withHeaders(['Accept-Language' => 'en']);

    register(registrationPayload(['city_id' => regionWithCity()->id]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.city_id.0', 'Choose an active city of the selected region.');
});

it('rate limits registrations per address', function (): void {
    foreach (range(1, 5) as $attempt) {
        register(registrationPayload(['official_email' => "info{$attempt}@alamal.example.sa"]))->assertCreated();
    }

    register(registrationPayload(['official_email' => 'info6@alamal.example.sa']))->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});

it('never deletes an unverified registration automatically', function (): void {
    register(registrationPayload())->assertCreated();
    $owner = registeredOwner();

    $this->travel(800)->days();
    Artisan::call('moashir:prune-otp-codes');
    Artisan::call('moashir:expire-invitations');

    expect(Entity::query()->whereKey($owner->entity_id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($owner->id)->exists())->toBeTrue();
});
