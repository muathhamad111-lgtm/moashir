<?php

declare(strict_types=1);

use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileSection;
use App\Enums\Identity\RoleKey;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Support\Entities\EntityPresenter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const HISTORY_AUTHORIZED_PERSON = 'سلمان بن فهد العتيبي';

const HISTORY_PHONE = '+966551234567';

const HISTORY_EDIT_REASON = 'تصحيح عدد المستفيدين بناءً على تقرير الجهة السنوي.';

const HISTORY_ITEM_KEYS = ['id', 'field', 'section', 'field_label', 'old_value', 'new_value', 'changed_by', 'changed_at'];

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 09:00:00');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function unchangedProfileOf(Entity $entity, array $overrides = []): array
{
    $owner = ownerOf($entity);

    return [
        'name' => $entity->name->toArray(),
        'authorized_person_name' => $owner->name,
        'phone' => $owner->phone,
        'entity_type' => $entity->entity_type->value,
        'development_sector' => $entity->development_sector->value,
        'org_size' => $entity->org_size->value,
        'founding_year' => $entity->founding_year,
        'region_id' => $entity->region_id,
        'city_id' => $entity->city_id,
        'governance_grade' => $entity->governance_grade,
        'employees_count' => $entity->employees_count,
        'annual_beneficiaries_count' => $entity->annual_beneficiaries_count,
        'has_strategic_plan' => $entity->has_strategic_plan,
        'marketing_consent' => false,
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function editOwnProfile(Entity $entity, array $overrides, ProfileSection $section = ProfileSection::Governance): void
{
    saveSection($entity, $section, $overrides)->assertOk();
}

it('lists the own profile changes newest first with bilingual labels and the names of the places', function (): void {
    $entity = entityWithOwner();
    $previousCity = City::query()->with('region')->findOrFail($entity->city_id);
    $newCity = regionWithCity()->load('region');
    editOwnProfile($entity, ['governance_grade' => '91.5']);
    Date::setTestNow(Date::now()->addHour());
    editOwnProfile($entity, ['region_id' => $newCity->region_id, 'city_id' => $newCity->id], ProfileSection::Classification);

    $response = switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes')
        ->assertOk()
        ->assertJsonPath('meta.pagination', ['page' => 1, 'per_page' => 15, 'total' => 3, 'last_page' => 1])
        ->assertJsonPath('data.0.field', 'city_id')
        ->assertJsonPath('data.0.old_value', EntityPresenter::place($previousCity))
        ->assertJsonPath('data.0.new_value', EntityPresenter::place($newCity))
        ->assertJsonPath('data.0.section', 'classification')
        ->assertJsonPath('data.1.field', 'region_id')
        ->assertJsonPath('data.1.field_label', ['ar' => 'المنطقة', 'en' => 'Region'])
        ->assertJsonPath('data.1.old_value', EntityPresenter::place($previousCity->region))
        ->assertJsonPath('data.1.new_value', EntityPresenter::place($newCity->region))
        ->assertJsonPath('data.1.changed_by', 'entity')
        ->assertJsonPath('data.1.changed_at', '2026-11-01T10:00:00+00:00')
        ->assertJsonPath('data.2.field', 'governance_grade')
        ->assertJsonPath('data.2.section', 'governance')
        ->assertJsonPath('data.2.field_label', ['ar' => 'درجة الحوكمة', 'en' => 'Governance grade'])
        ->assertJsonPath('data.2.old_value', '82.50')
        ->assertJsonPath('data.2.new_value', '91.50')
        ->assertJsonPath('data.2.changed_at', '2026-11-01T09:00:00+00:00');

    $owner = ownerOf($entity);

    expect(array_keys($response->json('data.0')))->toBe(HISTORY_ITEM_KEYS)
        ->and($response->getContent())->not->toContain($owner->email)
        ->and($response->getContent())->not->toContain(HISTORY_AUTHORIZED_PERSON)
        ->and($response->getContent())->not->toContain(HISTORY_PHONE)
        ->and($response->getContent())->not->toContain('changed_by_user_id');
});

it('labels every profile field in Arabic and English', function (EntityProfileField $field): void {
    expect(__($field->labelKey(), [], 'ar'))->not->toBe($field->labelKey())
        ->and(__($field->labelKey(), [], 'en'))->not->toBe($field->labelKey());
})->with(EntityProfileField::cases());

it('shows a staff edit on behalf as a staff change without naming the staff member', function (): void {
    $entity = entityWithOwner();
    $admin = systemAdmin();
    switchTo($admin)->putJson("/api/v1/entities/{$entity->id}", [...unchangedProfileOf($entity, ['annual_beneficiaries_count' => 12000]), 'reason' => HISTORY_EDIT_REASON])->assertOk();

    $response = switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.field', 'annual_beneficiaries_count')
        ->assertJsonPath('data.0.new_value', 12000)
        ->assertJsonPath('data.0.changed_by', 'staff');

    expect($response->getContent())->not->toContain($admin->email)
        ->and($response->getContent())->not->toContain($admin->name)
        ->and($response->getContent())->not->toContain(HISTORY_EDIT_REASON);
});

it('shows a change without an actor as a system change', function (): void {
    $entity = entityWithOwner();
    EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'has_strategic_plan', 'old_value' => false, 'new_value' => true]);

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes')
        ->assertOk()
        ->assertJsonPath('data.0.changed_by', 'system')
        ->assertJsonPath('data.0.old_value', false)
        ->assertJsonPath('data.0.new_value', true);
});

it('keeps the own history to the own entity', function (): void {
    $entity = entityWithOwner();
    $other = entityWithOwner();
    editOwnProfile($other, ['employees_count' => 3]);

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0)
        ->assertJsonPath('data', []);
});

it('lets staff with the full entity view read the history of an entity', function (RoleKey $role): void {
    $entity = entityWithOwner();
    editOwnProfile($entity, ['employees_count' => 3]);

    switchTo(staffUser($role))->getJson("/api/v1/entities/{$entity->id}/profile-changes")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.field', 'employees_count')
        ->assertJsonPath('data.0.new_value', 3)
        ->assertJsonPath('data.0.changed_by', 'entity');
})->with([RoleKey::SystemAdmin, RoleKey::RequestFollower]);

it('lets the Super Admin read the history of an entity', function (): void {
    $entity = entityWithOwner();
    editOwnProfile($entity, ['employees_count' => 3]);

    switchTo(superAdmin())->getJson("/api/v1/entities/{$entity->id}/profile-changes")->assertOk()->assertJsonPath('meta.pagination.total', 1);
});

it('denies the history of an entity to staff without the full entity view', function (RoleKey $role, int $status): void {
    $entity = entityWithOwner();

    switchTo(staffUser($role))->getJson("/api/v1/entities/{$entity->id}/profile-changes")->assertStatus($status);
})->with([
    'finance officer, contact view only' => [RoleKey::FinanceOfficer, 403],
    'assessor, no entity in scope' => [RoleKey::Assessor, 404],
    'criteria officer, no entities.view' => [RoleKey::CriteriaOfficer, 403],
]);

it('keeps the history of a deleted entity to holders of entities.delete', function (): void {
    $entity = entityWithOwner();
    editOwnProfile($entity, ['employees_count' => 3]);
    softDeleteWithOpenRequests($entity, []);

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/profile-changes")->assertOk()->assertJsonPath('meta.pagination.total', 1);
    switchTo(staffUser(RoleKey::RequestFollower))->getJson("/api/v1/entities/{$entity->id}/profile-changes")->assertNotFound();
});

it('keeps each history endpoint to its audience', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf(entityWithOwner()))->getJson("/api/v1/entities/{$entity->id}/profile-changes")->assertForbidden();
    switchTo(systemAdmin())->getJson('/api/v1/entities/me/profile-changes')->assertForbidden()->assertJsonPath('code', 'forbidden');
    switchTo(systemAdmin())->getJson('/api/v1/entities/999999/profile-changes')->assertNotFound();
});

it('requires a session for both history endpoints', function (string $uri): void {
    $this->getJson($uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
})->with(['/api/v1/entities/me/profile-changes', '/api/v1/entities/1/profile-changes']);

it('paginates the history', function (): void {
    $entity = entityWithOwner();
    editOwnProfile($entity, ['employees_count' => 3]);
    Date::setTestNow(Date::now()->addHour());
    editOwnProfile($entity, ['employees_count' => 4]);

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/profile-changes?per_page=1&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.new_value', 3)
        ->assertJsonPath('meta.pagination', ['page' => 2, 'per_page' => 1, 'total' => 2, 'last_page' => 2]);
});

it('rejects invalid pagination', function (string $query, string $field): void {
    $entity = entityWithOwner();

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/profile-changes?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'per page zero' => ['per_page=0', 'per_page'],
    'per page above 100' => ['per_page=101', 'per_page'],
    'page zero' => ['page=0', 'page'],
    'page not a number' => ['page=first', 'page'],
]);

it('reads a page of the history with the same number of queries whatever its size', function (): void {
    $entity = entityWithOwner();

    foreach (range(1, 12) as $index) {
        $city = regionWithCity();
        EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'region_id', 'old_value' => $entity->region_id, 'new_value' => $city->region_id]);
        EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'city_id', 'old_value' => $entity->city_id, 'new_value' => $city->id]);
        EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'employees_count', 'old_value' => $index, 'new_value' => $index + 1]);
    }

    switchTo(systemAdmin());
    $queriesFor = function (int $perPage) use ($entity): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/v1/entities/{$entity->id}/profile-changes?per_page={$perPage}")->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $queriesFor(1);

    expect($queriesFor(3))->toBe($queriesFor(30));
});

it('is not reachable for a role holding only the privacy permission', function (): void {
    grantPrivacyToContentOfficers();
    $entity = entityWithOwner();

    switchTo(staffUser(RoleKey::ContentOfficer))->getJson("/api/v1/entities/{$entity->id}/profile-changes")->assertForbidden();
});

it('names the staff member and their roles on the staff history only', function (): void {
    $entity = entityWithOwner();
    $admin = staffUser(RoleKey::SystemAdmin, RoleKey::RequestFollower);
    switchTo($admin)->putJson("/api/v1/entities/{$entity->id}", [...unchangedProfileOf($entity, ['employees_count' => 2]), 'reason' => HISTORY_EDIT_REASON])->assertOk();
    Date::setTestNow(Date::now()->addHour());
    editOwnProfile($entity, ['employees_count' => 1]);

    $staffView = switchTo(staffUser(RoleKey::RequestFollower))->getJson("/api/v1/entities/{$entity->id}/profile-changes")
        ->assertOk()
        ->assertJsonPath('data.0.changed_by', 'entity')
        ->assertJsonPath('data.0.changed_by_staff', null)
        ->assertJsonPath('data.1.changed_by', 'staff')
        ->assertJsonPath('data.1.changed_by_staff', ['name' => $admin->name, 'roles' => [
            ['key' => 'system_admin', 'label' => ['ar' => __('roles.system_admin.label', [], 'ar'), 'en' => __('roles.system_admin.label', [], 'en')]],
            ['key' => 'request_follower', 'label' => ['ar' => __('roles.request_follower.label', [], 'ar'), 'en' => __('roles.request_follower.label', [], 'en')]],
        ]]);

    expect(array_keys($staffView->json('data.1')))->toBe([...HISTORY_ITEM_KEYS, 'changed_by_staff'])
        ->and($staffView->getContent())->not->toContain($admin->email)
        ->and($staffView->getContent())->not->toContain(HISTORY_EDIT_REASON);

    $ownView = switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes')->assertOk();

    expect(array_keys($ownView->json('data.1')))->toBe(HISTORY_ITEM_KEYS)
        ->and($ownView->getContent())->not->toContain($admin->name)
        ->and($ownView->getContent())->not->toContain('changed_by_staff');
});

it('shows no name and no roles for a staff member who no longer exists or is a Super Admin', function (string $who): void {
    $entity = entityWithOwner();
    $author = $who === 'super admin' ? superAdmin() : staffUser(RoleKey::SystemAdmin);
    EntityProfileChange::query()->create(['entity_id' => $entity->id, 'changed_by_user_id' => $author->id, 'field' => 'employees_count', 'old_value' => 4, 'new_value' => 5]);

    if ($who === 'deleted staff member') {
        $author->delete();
    }

    $response = switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/profile-changes")
        ->assertOk()
        ->assertJsonPath('data.0.changed_by', 'staff')
        ->assertJsonPath('data.0.changed_by_staff', ['name' => null, 'roles' => []]);

    expect($response->getContent())->not->toContain($author->name);
})->with(['deleted staff member', 'super admin']);

it('loads the staff authors of a page with a fixed number of queries', function (): void {
    $entity = entityWithOwner();
    $authors = [staffUser(RoleKey::SystemAdmin), staffUser(RoleKey::RequestFollower), staffUser(RoleKey::SystemAdmin)];

    foreach (range(1, 12) as $index) {
        EntityProfileChange::query()->create(['entity_id' => $entity->id, 'changed_by_user_id' => $authors[$index % 3]->id, 'field' => 'employees_count', 'old_value' => $index, 'new_value' => $index + 1]);
    }

    switchTo(systemAdmin());
    $queriesFor = function (int $perPage) use ($entity): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/v1/entities/{$entity->id}/profile-changes?per_page={$perPage}")->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $queriesFor(1);

    expect($queriesFor(2))->toBe($queriesFor(12));
});

it('filters both histories by section', function (): void {
    $entity = entityWithOwner();
    $newCity = regionWithCity();
    editOwnProfile($entity, ['employees_count' => 3]);
    editOwnProfile($entity, ['region_id' => $newCity->region_id, 'city_id' => $newCity->id], ProfileSection::Classification);
    editOwnProfile($entity, ['authorized_person_name' => HISTORY_AUTHORIZED_PERSON, 'phone' => '0551234567', 'marketing_consent' => true], ProfileSection::Contact);

    $fieldsOf = static fn (array $items): array => collect($items)->pluck('field')->sort()->values()->all();
    $own = switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes?section=classification')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2);
    $staff = switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/profile-changes?section=contact")->assertOk()
        ->assertJsonPath('meta.pagination.total', 3);

    expect($fieldsOf($own->json('data')))->toBe(['city_id', 'region_id'])
        ->and(collect($own->json('data'))->pluck('section')->unique()->all())->toBe(['classification'])
        ->and($fieldsOf($staff->json('data')))->toBe(['authorized_person_name', 'marketing_consent', 'phone']);

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes?section=identity')->assertOk()->assertJsonPath('data', []);
    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes')->assertOk()->assertJsonPath('meta.pagination.total', 6);
});

it('shows the contact history with the phone masked and the actor kind only', function (): void {
    $entity = entityWithOwner();
    editOwnProfile($entity, ['phone' => '0551234567'], ProfileSection::Contact);

    $response = switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes?section=contact')->assertOk()
        ->assertJsonPath('data.0.field', 'phone')
        ->assertJsonPath('data.0.section', 'contact')
        ->assertJsonPath('data.0.field_label', ['ar' => 'رقم الجوال', 'en' => 'Mobile number'])
        ->assertJsonPath('data.0.old_value', null)
        ->assertJsonPath('data.0.new_value', '+9665*****567')
        ->assertJsonPath('data.0.changed_by', 'entity');

    expect($response->getContent())->not->toContain(HISTORY_PHONE)->not->toContain('551234567');
});

it('rejects an unknown history section', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes?section=logo')->assertUnprocessable()->assertJsonValidationErrors(['section']);
    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/profile-changes?section=owner")->assertUnprocessable()->assertJsonValidationErrors(['section']);
});
