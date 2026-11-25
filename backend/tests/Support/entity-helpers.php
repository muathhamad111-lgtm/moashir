<?php

declare(strict_types=1);

use App\Data\Entities\OpenEntityRequest;
use App\Data\Entities\RegistrationDocuments;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityDocument;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Entities\ProfileSection;
use App\Enums\Identity\TermsKind;
use App\Enums\Settings\SettingKey;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Entities\EntityEraser;
use App\Services\Entities\OpenRequestDirectory;
use App\Services\Entities\RegistrationEmailCodes;
use App\Services\Entities\RegistrationEmailTokens;
use App\Services\Settings\SettingsStore;
use App\Support\Entities\ProfileFieldValues;
use App\Support\Entities\ProfileSectionRevision;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeOpenRequestSource;
use Tests\Support\RecordingErasureStep;
use Tests\TestCase;

function publishTerms(TermsKind $kind = TermsKind::Terms, string $version = '2026-11'): TermsVersion
{
    return TermsVersion::query()->forceCreate([
        'kind' => $kind->value,
        'version' => $version,
        'body' => ['ar' => 'نص الشروط والأحكام المعتمد لمنصة مؤشر.', 'en' => 'The approved terms and conditions of the Moashir platform.'],
        'effective_from' => Date::today()->toDateString(),
        'is_current' => true,
        'published_at' => Date::now(),
        'published_by_user_id' => User::factory()->create(['name' => 'هند الزهراني'])->id,
    ]);
}

function regionWithCity(): City
{
    $region = Region::factory()->create(['code' => 'riyadh_'.fake()->unique()->numberBetween(1, 99_999)]);

    return City::factory()->create(['region_id' => $region->id, 'name' => ['ar' => 'الرياض', 'en' => 'Riyadh']]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    $city = regionWithCity();
    $terms = TermsVersion::query()->current()->where('kind', TermsKind::Terms->value)->first() ?? publishTerms();

    $payload = [
        'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
        'authorized_person_name' => 'سلمان بن فهد العتيبي',
        'official_email' => 'info@alamal.example.sa',
        'unified_number' => '7001234567',
        'license_number' => '44'.(is_string($overrides['unified_number'] ?? null) ? $overrides['unified_number'] : '7001234567'),
        'phone' => '0551234567',
        'password' => 'Wadi-Hanifa-Spring-2026',
        'password_confirmation' => 'Wadi-Hanifa-Spring-2026',
        'entity_type' => 'civil_association',
        'development_sector' => 'social',
        'org_size' => 'medium',
        'founding_year' => 2011,
        'region_id' => $city->region_id,
        'city_id' => $city->id,
        'has_governance_grade' => true,
        'governance_grade' => '87.45',
        'employees_count' => 42,
        'annual_beneficiaries_count' => 8500,
        'has_strategic_plan' => true,
        'terms_version_id' => $terms->id,
        'terms_accepted' => true,
        'marketing_consent' => false,
        'license_certificate' => entityDocumentPdf('شهادة-الترخيص.pdf'),
        'authorization_letter' => entityDocumentPdf('خطاب-التفويض.pdf'),
        ...$overrides,
    ];

    return withEmailVerificationToken($payload, $overrides);
}

function entityDocumentPdf(string $name, string $contents = SAMPLE_PDF): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function registrationDocuments(): RegistrationDocuments
{
    return new RegistrationDocuments([
        EntityDocument::LicenseCertificate->value => entityDocumentPdf('شهادة-الترخيص.pdf'),
        EntityDocument::AuthorizationLetter->value => entityDocumentPdf('خطاب-التفويض.pdf'),
    ]);
}

/**
 * Adds a token proving the payload's official e-mail, unless the test sets the token itself or the
 * address is not a valid e-mail (the request is then refused before the token is looked at).
 *
 * @param  array<string, mixed>  $payload
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function withEmailVerificationToken(array $payload, array $overrides): array
{
    $email = $payload['official_email'] ?? null;

    if (array_key_exists('email_verification_token', $overrides) || ! is_string($email) || filter_var(mb_strtolower(trim($email)), FILTER_VALIDATE_EMAIL) === false) {
        return $payload;
    }

    return [...$payload, 'email_verification_token' => emailVerificationTokenFor($email)];
}

function emailVerificationTokenFor(string $email): string
{
    $verification = app(RegistrationEmailCodes::class)->open(new EmailAddress($email), '000000');
    $verification->forceFill(['consumed_at' => Date::now()])->save();

    return app(RegistrationEmailTokens::class)->issue($verification)->token;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profilePayload(Entity $entity, array $overrides = []): array
{
    return [
        'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
        'authorized_person_name' => 'سلمان بن فهد العتيبي',
        'phone' => '+966551234567',
        'entity_type' => 'civil_association',
        'development_sector' => 'social',
        'org_size' => 'medium',
        'founding_year' => 2011,
        'region_id' => $entity->region_id,
        'city_id' => $entity->city_id,
        'has_governance_grade' => true,
        'governance_grade' => '87.45',
        'employees_count' => 42,
        'annual_beneficiaries_count' => 8500,
        'has_strategic_plan' => true,
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sectionBody(Entity $entity, ProfileSection $section, array $overrides = []): array
{
    $values = ProfileFieldValues::of($entity->refresh());
    $body = ['base_revision' => ProfileSectionRevision::of($values, $section)];

    foreach ($values->editableValuesOf($section) as $field => $value) {
        data_set($body, $field, $value);
    }

    return [...$body, ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function saveSection(Entity $entity, ProfileSection $section, array $overrides = [], ?User $as = null): TestResponse
{
    $body = sectionBody($entity, $section, $overrides);

    return switchTo($as ?? ownerOf($entity))->patchJson('/api/v1/entities/me/profile/'.$section->value, $body);
}

function entityWithOwner(?Closure $state = null): Entity
{
    $factory = Entity::factory()->withOwner();

    return ($state instanceof Closure ? $state($factory) : $factory)->create()->refresh();
}

function requireEntityRegistrationReview(bool $required): void
{
    app(SettingsStore::class)->put(SettingKey::EntityRegistrationReview, $required, AuditEvent::AuthSettingsUpdated, null);
}

function ownerOf(Entity $entity): User
{
    return User::query()->where('entity_id', $entity->id)->firstOrFail();
}

function openRequest(string $number, EntityRequestPath $path = EntityRequestPath::Accredited, int $id = 1): OpenEntityRequest
{
    return new OpenEntityRequest($id, $number, $path, 'under_review');
}

/**
 * @param  list<OpenEntityRequest>  $requests
 */
function giveOpenRequests(Entity $entity, array $requests): FakeOpenRequestSource
{
    if (! app()->bound(FakeOpenRequestSource::class)) {
        app()->instance(FakeOpenRequestSource::class, new FakeOpenRequestSource);
        app()->tag([FakeOpenRequestSource::class], OpenRequestDirectory::TAG);
    }

    $source = app(FakeOpenRequestSource::class);
    $source->give($entity, $requests);

    return $source;
}

function switchTo(User $user): TestCase
{
    test()->flushSession();
    app('auth')->forgetGuards();

    return signIn($user);
}

function otpCodeSentTo(User $user): string
{
    $code = '';

    Notification::assertSentTo($user, OtpCodeNotification::class, static function (OtpCodeNotification $notification) use ($user, &$code): bool {
        $code = (string) $notification->toMail($user)->code;

        return true;
    });

    return $code;
}

function otpCodeSentToAddress(string $address): string
{
    $code = '';

    Notification::assertSentOnDemand(OtpCodeNotification::class, static function (OtpCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($address, &$code): bool {
        if (($notifiable->routes['mail'] ?? null) !== $address) {
            return false;
        }

        $code = (string) $notification->toMail($notifiable)->code;

        return true;
    });

    return $code;
}

function recordErasureSteps(): RecordingErasureStep
{
    $step = new RecordingErasureStep;
    app()->instance(RecordingErasureStep::class, $step);
    app()->tag([RecordingErasureStep::class], EntityEraser::TAG);

    return $step;
}
