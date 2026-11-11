<?php

declare(strict_types=1);

use App\Data\Entities\OpenEntityRequest;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Entities\ProfileSection;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Models\Identity\Role;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Entities\EntityEraser;
use App\Services\Entities\OpenRequestDirectory;
use App\Support\Entities\ProfileFieldValues;
use App\Support\Entities\ProfileSectionRevision;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
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

    return [
        'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
        'authorized_person_name' => 'سلمان بن فهد العتيبي',
        'official_email' => 'info@alamal.example.sa',
        'unified_number' => '7001234567',
        'phone' => '0551234567',
        'password' => 'Wadi-Hanifa-Spring-2026',
        'password_confirmation' => 'Wadi-Hanifa-Spring-2026',
        'entity_type' => 'civil_association',
        'development_sector' => 'social',
        'org_size' => 'medium',
        'founding_year' => 2011,
        'region_id' => $city->region_id,
        'city_id' => $city->id,
        'governance_grade' => '87.45',
        'employees_count' => 42,
        'annual_beneficiaries_count' => 8500,
        'has_strategic_plan' => true,
        'terms_version_id' => $terms->id,
        'terms_accepted' => true,
        'marketing_consent' => false,
        ...$overrides,
    ];
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

function grantPrivacyToContentOfficers(): void
{
    Role::findByName(RoleKey::ContentOfficer->value)->givePermissionTo(PermissionKey::PrivacyRequestsManage->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}
