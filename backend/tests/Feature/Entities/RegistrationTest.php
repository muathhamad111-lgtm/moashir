<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Enums\Entities\ProfileSection;
use App\Enums\Entities\ProfileSectionField;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Identity\RegistrationEmailVerification;
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

const REGISTRATION_BILLING_GAPS_AR = 'رقم المبنى، الشارع، الحي، المدينة (العنوان الوطني)، الرمز البريدي، الرقم الإضافي، التسجيل في ضريبة القيمة المضافة';

const REGISTRATION_BILLING_GAPS_EN = 'Building number, Street, District, City (national address), Postal code, Additional number, VAT registration';

beforeEach(function (): void {
    Notification::fake();
});

it('registers an entity with the verified official e-mail, holds it for staff review and opens no session', function (): void {
    $administrator = systemAdmin();

    register(registrationPayload())
        ->assertCreated()
        ->assertExactJson(['data' => ['step' => 'pending_review', 'message' => __('messages.registration_pending_review')], 'meta' => [], 'errors' => null]);

    $this->getJson('/api/v1/auth/session')->assertUnauthorized();
    $owner = registeredOwner();
    $entity = Entity::query()->findOrFail($owner->entity_id);
    $verified = Audit::query()->where('event', AuditEvent::EntityVerified->value)->sole();

    expect($entity->status)->toBe(EntityStatus::PendingReview)
        ->and($entity->verified_at)->not->toBeNull()
        ->and($entity->approved_at)->toBeNull()
        ->and($entity->name->ar)->toBe('جمعية الأمل الخيرية')
        ->and($entity->governance_grade)->toBe('87.45')
        ->and($entity->hasCompleteProfile())->toBeFalse()
        ->and($entity->profileCompletion()->percent())->toBe(59)
        ->and($entity->unified_number)->toBe('7001234567')
        ->and($owner->roleKeys())->toBe([RoleKey::Entity])
        ->and($owner->name)->toBe('سلمان بن فهد العتيبي')
        ->and($owner->phone)->toBe('+966551234567')
        ->and($owner->email_verified_at)->not->toBeNull()
        ->and($owner->mfa_required)->toBeFalse()
        ->and($owner->passwordHistories()->count())->toBe(1)
        ->and($verified->new_values)->toBe(['status' => 'pending_review']);
    Notification::assertSentTo($administrator, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityAwaitingReview);
    Notification::assertNotSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => in_array($notification->notice->code, [NotificationCode::EntityProfileIncomplete, NotificationCode::EntityRegistrationApproved], true));
    Notification::assertNotSentTo($owner, OtpCodeNotification::class);
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

it('accepts an unanswered strategic plan and counts it as missing', function (): void {
    register(registrationPayload(['has_strategic_plan' => null]))->assertCreated();

    $entity = Entity::query()->firstOrFail();

    expect($entity->has_strategic_plan)->toBeNull()
        ->and($entity->profileCompletion()->toArray()['sections'][2]['missing_fields'])->toBe(['has_strategic_plan']);
});

it('notifies the entity and the system administrators of the registration', function (): void {
    $admin = systemAdmin();

    register(registrationPayload())->assertCreated();

    Notification::assertSentTo(registeredOwner(), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityRegistered);
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityRegistered);
});

it('audits the registration without personal data', function (): void {
    register(registrationPayload())->assertCreated();

    $audit = Audit::query()->where('event', AuditEvent::EntityRegistered->value)->firstOrFail();
    $everyAudit = json_encode(Audit::query()->get(['old_values', 'new_values'])->toArray(), JSON_UNESCAPED_UNICODE);

    expect($everyAudit)->not->toContain('info@alamal.example.sa')
        ->not->toContain('سلمان')
        ->and($audit->new_values)->toHaveKeys(['status', 'entity_type', 'region_id', 'marketing_consent', 'terms_version_ids']);
});

it('consumes the e-mail token so it cannot register a second time', function (): void {
    $payload = registrationPayload();

    register($payload)->assertCreated();
    register([...$payload, 'unified_number' => '7001234568'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'registration.email_unverified')
        ->assertJsonPath('errors.official_email.0', __('errors.registration.email_unverified'));

    expect(Entity::query()->count())->toBe(1)
        ->and(RegistrationEmailVerification::query()->sole()->token_used_at)->not->toBeNull();
});

it('activates the entity and signs it in on registration while the review is switched off', function (): void {
    requireEntityRegistrationReview(false);
    $administrator = systemAdmin();

    register(registrationPayload(['employees_count' => null]))
        ->assertCreated()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.entity.status', 'active')
        ->assertJsonPath('data.user.entity.profile_completed', false)
        ->assertJsonPath('data.user.entity.name.ar', 'جمعية الأمل الخيرية');

    $owner = registeredOwner();
    $this->assertAuthenticatedAs($owner, 'web');
    $entity = Entity::query()->findOrFail($owner->entity_id);
    $audit = Audit::query()->where('event', AuditEvent::EntityVerified->value)->sole();

    expect($entity->status)->toBe(EntityStatus::Active)
        ->and($entity->verified_at)->not->toBeNull()
        ->and($entity->approved_at)->not->toBeNull()
        ->and($owner->email_verified_at)->not->toBeNull()
        ->and($owner->last_login_at)->not->toBeNull()
        ->and($audit->new_values)->toBe(['status' => 'active', 'approval' => 'automatic']);
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileIncomplete);
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityRegistrationApproved);
    Notification::assertNotSentTo($administrator, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityAwaitingReview);
    Notification::assertNotSentTo($owner, OtpCodeNotification::class);
});

it('does not ask a legacy entity with a complete profile to complete it once it confirms its e-mail', function (): void {
    requireEntityRegistrationReview(false);
    $owner = ownerOf(entityWithOwner(static fn ($factory) => $factory->pendingVerification()));
    $challengeId = signInWith($owner->email, defaultPassword())->assertOk()->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => otpCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated')
        ->assertJsonPath('data.user.entity.profile_completed', true);

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityRegistrationApproved);
    Notification::assertNotSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileIncomplete);
});

it('names only the profile fields still missing in the completion reminder', function (): void {
    requireEntityRegistrationReview(false);

    register(registrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.user.entity.profile_completed', false);

    Notification::assertSentTo(registeredOwner(), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileIncomplete
        && $notification->notice->body->ar === 'تم التحقق من حسابك. لاستكمال الملف التعريفي لـ جمعية الأمل الخيرية أضف: رقم الترخيص، تاريخ التأسيس، '.REGISTRATION_BILLING_GAPS_AR.'.'
        && $notification->notice->body->en === 'Your account is verified. To complete the profile of Al-Amal Charitable Association, add: Licence number, Founding date, '.REGISTRATION_BILLING_GAPS_EN.'.');
});

it('names the governance figures in the completion reminder when the registration leaves them empty', function (): void {
    requireEntityRegistrationReview(false);

    register(registrationPayload(['license_number' => '1234', 'founded_on' => '2011-04-20', 'governance_grade' => null, 'employees_count' => null]))->assertCreated();

    Notification::assertSentTo(registeredOwner(), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileIncomplete
        && str_ends_with($notification->notice->body->ar, 'أضف: درجة الحوكمة، عدد الموظفين، '.REGISTRATION_BILLING_GAPS_AR.'.')
        && str_ends_with($notification->notice->body->en, 'add: Governance grade, Number of employees, '.REGISTRATION_BILLING_GAPS_EN.'.'));
});

it('leaves only the billing details to complete when the registration carries the licence number and the founding date', function (): void {
    requireEntityRegistrationReview(false);

    register(registrationPayload(['license_number' => '1234', 'founded_on' => '2011-04-20']))
        ->assertCreated()
        ->assertJsonPath('data.user.entity.profile_completed', false);

    $entity = Entity::query()->firstOrFail();

    expect($entity->license_number)->toBe('1234')
        ->and($entity->founded_on?->toDateString())->toBe('2011-04-20')
        ->and($entity->founding_year)->toBe(2011)
        ->and(array_map(static fn (ProfileSectionField $field): ProfileSection => $field->section(), $entity->profileCompletion()->missingFields()))
        ->each->toBe(ProfileSection::Billing);
    Notification::assertSentTo(registeredOwner(), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityProfileIncomplete
        && str_ends_with($notification->notice->body->ar, 'أضف: '.REGISTRATION_BILLING_GAPS_AR.'.'));
});

it('refuses a licence number already held by another entity', function (): void {
    Entity::factory()->create(['license_number' => '5678']);

    register(registrationPayload(['license_number' => '5678']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['license_number']);

    expect(Entity::query()->count())->toBe(1);
});

it('takes the founding year from the founding date when the form sends only the date', function (): void {
    $payload = registrationPayload(['founded_on' => '2009-12-31']);
    unset($payload['founding_year']);

    register($payload)->assertCreated();

    expect(Entity::query()->firstOrFail()->founding_year)->toBe(2009);
});

it('asks for two-factor enrolment instead of opening a session when the entity role requires it', function (): void {
    requireEntityRegistrationReview(false);
    signIn(systemAdmin())->putJson('/api/v1/settings/auth', ['two_factor_roles' => ['entity']])->assertOk();
    $this->flushSession();
    app('auth')->forgetGuards();

    register(registrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.step', 'mfa_enrollment');

    $this->assertGuest('web');
    expect(registeredOwner()->mfa_required)->toBeTrue();
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

it('asks a legacy entity still awaiting e-mail confirmation for an e-mail code when it signs in with its password', function (): void {
    $owner = ownerOf(entityWithOwner(static fn ($factory) => $factory->pendingVerification()));

    signInWith($owner->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'otp_required')
        ->assertJsonPath('data.requires_otp', true);

    $this->getJson('/api/v1/auth/session')->assertUnauthorized();
    expect(Entity::query()->findOrFail($owner->entity_id)->status)->toBe(EntityStatus::PendingVerification);
    Notification::assertSentToTimes($owner, OtpCodeNotification::class, 1);
});

it('refuses an already registered e-mail once its owner has proven it and creates nothing', function (): void {
    entityWithOwner()->owner?->forceFill(['email' => 'info@alamal.example.sa'])->save();
    $entities = Entity::query()->count();

    register(registrationPayload())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'registration.email_taken')
        ->assertJsonPath('errors.official_email.0', __('errors.registration.email_taken'));

    expect(Entity::query()->count())->toBe($entities)
        ->and(RegistrationEmailVerification::query()->sole()->token_used_at)->toBeNull();
    Notification::assertNothingSent();
});

it('treats the official e-mail case-insensitively for duplicates', function (): void {
    entityWithOwner()->owner?->forceFill(['email' => 'info@alamal.example.sa'])->save();

    register(registrationPayload(['official_email' => 'INFO@ALAMAL.example.sa']))->assertUnprocessable()->assertJsonPath('code', 'registration.email_taken');

    expect(User::query()->whereRaw('lower(email) = ?', ['info@alamal.example.sa'])->count())->toBe(1);
});

it('refuses a registration whose official e-mail was not verified', function (Closure $token): void {
    register(registrationPayload(['email_verification_token' => $token()]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'registration.email_unverified')
        ->assertJsonPath('errors.official_email.0', __('errors.registration.email_unverified'));

    expect(Entity::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'info@alamal.example.sa')->exists())->toBeFalse();
})->with([
    'missing' => [static fn (): ?string => null],
    'unknown' => [static fn (): string => str_repeat('x', 64)],
    'issued for another address' => [static fn (): string => emailVerificationTokenFor('other@alamal.example.sa')],
    'expired' => [static function (): string {
        $token = emailVerificationTokenFor('info@alamal.example.sa');
        test()->travel(31)->minutes();

        return $token;
    }],
]);

it('accepts the token of the same address typed in another letter case', function (): void {
    register(registrationPayload(['official_email' => 'Info@Alamal.example.sa', 'email_verification_token' => emailVerificationTokenFor('info@alamal.example.sa')]))->assertCreated();
});

it('keeps the e-mail token usable when the registration is refused for another field', function (): void {
    entityWithOwner(static fn ($factory) => $factory->state(['unified_number' => '7001234567']));
    $token = emailVerificationTokenFor('info@alamal.example.sa');

    register(registrationPayload(['email_verification_token' => $token]))->assertUnprocessable()->assertJsonValidationErrors(['unified_number']);
    register(registrationPayload(['email_verification_token' => $token, 'unified_number' => '7001234568']))->assertCreated();
});

it('rejects invalid registration data', function (Closure $mutate, string $field): void {
    register($mutate(registrationPayload()))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);

    expect(Entity::query()->count())->toBe(0);
})->with([
    'missing Arabic name' => [static fn (array $payload): array => [...$payload, 'name' => ['en' => 'Al-Amal']], 'name.ar'],
    'missing English name' => [static fn (array $payload): array => [...$payload, 'name' => ['ar' => 'جمعية الأمل الخيرية']], 'name.en'],
    'blank English name' => [static fn (array $payload): array => [...$payload, 'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => '  ']], 'name.en'],
    'unknown entity type' => [static fn (array $payload): array => [...$payload, 'entity_type' => 'family_fund'], 'entity_type'],
    'superseded entity type' => [static fn (array $payload): array => [...$payload, 'entity_type' => 'association'], 'entity_type'],
    'unknown sector' => [static fn (array $payload): array => [...$payload, 'development_sector' => 'sports'], 'development_sector'],
    'unknown size' => [static fn (array $payload): array => [...$payload, 'org_size' => 'huge'], 'org_size'],
    'founding year in the future' => [static fn (array $payload): array => [...$payload, 'founding_year' => (int) date('Y') + 1], 'founding_year'],
    'founding year too old' => [static fn (array $payload): array => [...$payload, 'founding_year' => 1850], 'founding_year'],
    'founding date in the future' => [static fn (array $payload): array => [...$payload, 'founded_on' => date('Y-m-d', strtotime('+2 days'))], 'founded_on'],
    'founding date not a calendar day' => [static fn (array $payload): array => [...$payload, 'founded_on' => '2011-02-30'], 'founded_on'],
    'founding date in another format' => [static fn (array $payload): array => [...$payload, 'founded_on' => '20/04/2011'], 'founded_on'],
    'licence number with letters' => [static fn (array $payload): array => [...$payload, 'license_number' => 'LIC-12'], 'license_number'],
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
        register(registrationPayload(['official_email' => "info{$attempt}@alamal.example.sa", 'unified_number' => "700123456{$attempt}"]))->assertCreated();
    }

    register(registrationPayload(['official_email' => 'info6@alamal.example.sa', 'unified_number' => '7001234566']))->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});

it('never deletes an unverified registration automatically', function (): void {
    $owner = ownerOf(entityWithOwner(static fn ($factory) => $factory->pendingVerification()));

    $this->travel(800)->days();
    Artisan::call('moashir:prune-otp-codes');
    Artisan::call('moashir:expire-invitations');

    expect(Entity::query()->whereKey($owner->entity_id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($owner->id)->exists())->toBeTrue();
});

it('asks an approved entity with an authenticator but an unconfirmed e-mail for the e-mail code before the authenticator', function (): void {
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill([...totpAttributes(), 'email_verified_at' => null])->save();

    $challengeId = signInWith($owner->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'otp_required')
        ->json('data.otp.challenge_id');

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => otpCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.step', 'mfa_challenge');

    $this->assertGuest('web');
    expect($owner->refresh()->email_verified_at)->not->toBeNull();
});
