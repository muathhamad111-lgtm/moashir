<?php

declare(strict_types=1);

use App\Actions\Entities\UpdateOwnEntityProfile;
use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Data\Entities\EntityRegistryUpdate;
use App\Enums\Entities\EntityProfileField;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Support\ValueObjects\LicenseNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function ownProfileBody(Entity $entity, array $extra = []): array
{
    return [...profilePayload($entity, ['founding_year' => $entity->founding_year]), 'marketing_consent' => false, ...$extra];
}

it('stores the licence number, the founding date and the normalised website and keeps the founding year on that date', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['founding_year' => 2005]));
    $owner = ownerOf($entity);

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, [
        'license_number' => '000123',
        'founded_on' => '2012-03-01',
        'website_url' => 'HTTPS://WWW.Alamal.Example.SA/',
    ]))->assertOk()
        ->assertJsonPath('data.license_number', '000123')
        ->assertJsonPath('data.founded_on', '2012-03-01')
        ->assertJsonPath('data.founding_year', 2012)
        ->assertJsonPath('data.website_url', 'https://www.alamal.example.sa')
        ->assertJsonPath('data.logo', null);

    $history = collect(switchTo($owner)->getJson('/api/v1/entities/me/profile-changes')->assertOk()->json('data'))->keyBy('field');

    expect($history->get('license_number'))->toMatchArray(['new_value' => '000123', 'field_label' => ['ar' => 'رقم الترخيص', 'en' => 'Licence number']])
        ->and($history->get('founded_on')['new_value'] ?? null)->toBe('2012-03-01')
        ->and($history->get('founding_year')['new_value'] ?? null)->toBe(2012)
        ->and($history->get('website_url')['new_value'] ?? null)->toBe('https://www.alamal.example.sa');
});

it('leaves the three registry details unchanged when an older client omits them and clears them on an explicit null', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['license_number' => '770000901', 'founding_year' => 2010, 'website_url' => 'https://alamal.example.sa']));
    $owner = ownerOf($entity);

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, ['employees_count' => 77]))->assertOk()
        ->assertJsonPath('data.license_number', '770000901')
        ->assertJsonPath('data.founded_on', '2010-03-01')
        ->assertJsonPath('data.website_url', 'https://alamal.example.sa');

    $changedFields = EntityProfileChange::query()->where('entity_id', $entity->id)->get()->map(static fn (EntityProfileChange $change): string => $change->field->value)->all();

    expect($changedFields)->toContain('employees_count')->not->toContain('license_number')->not->toContain('founded_on')->not->toContain('website_url');

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, ['license_number' => null, 'founded_on' => null, 'website_url' => '']))->assertOk()
        ->assertJsonPath('data.license_number', null)
        ->assertJsonPath('data.founded_on', null)
        ->assertJsonPath('data.website_url', null)
        ->assertJsonPath('data.profile_completed', false);
});

it('keeps the founding year on the stored founding date and takes the sent year once the date is cleared', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['founding_year' => 2012]));
    $owner = ownerOf($entity);

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, ['founding_year' => 1999]))->assertOk()
        ->assertJsonPath('data.founding_year', 2012)
        ->assertJsonPath('data.founded_on', '2012-03-01');

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, ['founding_year' => 1999, 'founded_on' => null]))->assertOk()
        ->assertJsonPath('data.founding_year', 1999)
        ->assertJsonPath('data.founded_on', null);
});

it('accepts today in Riyadh as a founding date', function (): void {
    Date::setTestNow('2026-11-04 22:30:00');
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->putJson('/api/v1/entities/me', ownProfileBody($entity, ['founded_on' => '2026-11-05']))->assertOk()
        ->assertJsonPath('data.founding_year', 2026);
});

it('rejects invalid registry details', function (array $details, string $field): void {
    Date::setTestNow('2026-11-04 10:00:00');
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->putJson('/api/v1/entities/me', ownProfileBody($entity, $details))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);
})->with([
    'licence with letters' => [['license_number' => 'SA-000123'], 'license_number'],
    'licence with two digits' => [['license_number' => '12'], 'license_number'],
    'licence with 21 digits' => [['license_number' => str_repeat('1', 21)], 'license_number'],
    'licence with Arabic-Indic digits' => [['license_number' => '١٢٣٤'], 'license_number'],
    'founding date in the future' => [['founded_on' => '2026-11-05'], 'founded_on'],
    'founding date before 1900' => [['founded_on' => '1899-12-31'], 'founded_on'],
    'founding date in another format' => [['founded_on' => '01/03/2012'], 'founded_on'],
    'http website' => [['website_url' => 'http://www.alamal.example.sa'], 'website_url'],
    'script website' => [['website_url' => 'javascript:alert(1)'], 'website_url'],
    'website with credentials' => [['website_url' => 'https://owner:secret@alamal.example.sa'], 'website_url'],
    'website on an IP address' => [['website_url' => 'https://10.0.0.8'], 'website_url'],
    'website longer than 255 characters' => [['website_url' => 'https://alamal.example.sa/'.str_repeat('a', 240)], 'website_url'],
]);

it('refuses a licence number registered for another entity with a clear message and keeps the own number', function (): void {
    entityWithOwner(static fn ($factory) => $factory->state(['license_number' => '770000555']));
    $deleted = entityWithOwner(static fn ($factory) => $factory->state(['license_number' => '770000666']));
    $deleted->delete();
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['license_number' => '770000777']));
    $owner = ownerOf($entity);

    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, ['license_number' => '770000555']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.license_number.0', __('validation.custom.license_number.unique'));
    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, ['license_number' => '770000666']))->assertUnprocessable();
    switchTo($owner)->putJson('/api/v1/entities/me', ownProfileBody($entity, ['license_number' => '770000777']))->assertOk();
});

it('turns a licence number taken by a concurrent request into the same validation error', function (): void {
    entityWithOwner(static fn ($factory) => $factory->state(['license_number' => '770000555']));
    $entity = entityWithOwner();
    $profile = new EntityProfileData(
        $entity->name, $entity->entity_type, $entity->development_sector, $entity->org_size, $entity->founding_year, $entity->region_id,
        $entity->city_id, $entity->governance_grade, $entity->employees_count, $entity->annual_beneficiaries_count, $entity->has_strategic_plan,
        new EntityRegistryUpdate(new LicenseNumber('770000555'), provided: [EntityProfileField::LicenseNumber]),
    );

    expect(fn () => app(UpdateOwnEntityProfile::class)->handle(ownerOf($entity), $profile, new EntityContactData('سلمان بن فهد العتيبي', null), false))
        ->toThrow(ValidationException::class);
    expect($entity->refresh()->license_number)->not->toBe('770000555');
});

it('guards the registry details in the database as well', function (Closure $write): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['founding_year' => 2012, 'license_number' => '770000901']));
    entityWithOwner(static fn ($factory) => $factory->state(['license_number' => '770000902']));

    expect(fn () => $write($entity))->toThrow(QueryException::class);
})->with([
    'founding date in another year' => [fn (Entity $entity) => DB::table('entities')->where('id', $entity->id)->update(['founded_on' => '2015-01-01'])],
    'licence with letters' => [fn (Entity $entity) => DB::table('entities')->where('id', $entity->id)->update(['license_number' => 'A123'])],
    'duplicate licence' => [fn (Entity $entity) => DB::table('entities')->where('id', $entity->id)->update(['license_number' => '770000902'])],
    'website without https' => [fn (Entity $entity) => DB::table('entities')->where('id', $entity->id)->update(['website_url' => 'http://alamal.example.sa'])],
]);

it('lets staff read and correct the registry details on behalf of the entity, with the same rule for absent fields', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->state(['license_number' => '770000901', 'website_url' => 'https://alamal.example.sa']));
    $admin = systemAdmin();
    $body = [...profilePayload($entity, ['founding_year' => $entity->founding_year]), 'reason' => 'تصحيح رقم الترخيص بعد مطابقته مع سجل المركز الوطني.'];

    switchTo($admin)->getJson("/api/v1/entities/{$entity->id}")->assertOk()
        ->assertJsonPath('data.license_number', '770000901')
        ->assertJsonPath('data.founded_on', $entity->founded_on?->toDateString())
        ->assertJsonPath('data.website_url', 'https://alamal.example.sa')
        ->assertJsonPath('data.logo', null);

    switchTo($admin)->putJson("/api/v1/entities/{$entity->id}", [...$body, 'license_number' => '770000911'])->assertOk()
        ->assertJsonPath('data.license_number', '770000911')
        ->assertJsonPath('data.website_url', 'https://alamal.example.sa');

    $history = collect(switchTo($admin)->getJson("/api/v1/entities/{$entity->id}/profile-changes?per_page=100")->assertOk()->json('data'))->keyBy('field');

    expect($history->get('license_number'))->toMatchArray(['old_value' => '770000901', 'new_value' => '770000911', 'changed_by' => 'staff'])
        ->and($history->has('website_url'))->toBeFalse();
});

it('keeps the registration form unchanged and never stores the registry details from it', function (): void {
    $this->postJson('/api/v1/auth/register', registrationPayload(['license_number' => '770000321', 'founded_on' => '2011-05-01', 'website_url' => 'https://alamal.example.sa']))
        ->assertCreated();

    $entity = Entity::query()->sole();

    expect($entity->license_number)->toBeNull()
        ->and($entity->founded_on)->toBeNull()
        ->and($entity->website_url)->toBeNull()
        ->and($entity->founding_year)->toBe(2011);
});
