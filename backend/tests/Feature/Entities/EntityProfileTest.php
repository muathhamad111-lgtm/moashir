<?php

declare(strict_types=1);

use App\Enums\Entities\ProfileSection;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Notification::fake();
});

it('returns the own entity profile to its single user', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    signIn($owner);

    $this->getJson('/api/v1/entities/me')
        ->assertOk()
        ->assertJsonPath('data.id', $entity->id)
        ->assertJsonPath('data.reference', $entity->reference())
        ->assertJsonPath('data.name', $entity->name->toArray())
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.official_email', $owner->email)
        ->assertJsonPath('data.authorized_person_name', $owner->name)
        ->assertJsonPath('data.region.id', $entity->region_id)
        ->assertJsonPath('data.city.id', $entity->city_id)
        ->assertJsonPath('data.governance_grade', '82.50')
        ->assertJsonPath('data.profile_completed', true)
        ->assertJsonPath('data.pending_email_change', null)
        ->assertJsonPath('data.can_edit', true)
        ->assertJsonMissingPath('data.deactivation_reason')
        ->assertJsonMissingPath('data.deletion_reason');
});

it('describes every section with its fields, their requirement levels and a revision', function (): void {
    $entity = entityWithOwner();

    $sections = switchTo(ownerOf($entity))->getJson('/api/v1/entities/me')->assertOk()->json('data.profile_sections');

    expect(array_column($sections, 'key'))->toBe(['identity', 'classification', 'governance', 'contact'])
        ->and($sections[0]['fields'])->toBe([
            ['key' => 'name.ar', 'requirement' => 'required'],
            ['key' => 'name.en', 'requirement' => 'optional'],
            ['key' => 'unified_number', 'requirement' => 'required'],
            ['key' => 'license_number', 'requirement' => 'completion'],
            ['key' => 'founded_on', 'requirement' => 'completion'],
        ])
        ->and($sections[3]['fields'][0])->toBe(['key' => 'official_email', 'requirement' => 'required'])
        ->and(array_column($sections, 'revision'))->each->toMatch('/^[0-9a-f]{32}$/')
        ->and($sections[2]['revision'])->toBe(sectionBody($entity, ProfileSection::Governance)['base_revision']);
});

it('denies the own profile to staff', function (): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities/me')->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('requires a session for the own profile', function (): void {
    $this->getJson('/api/v1/entities/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('no longer offers the whole-profile update', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->putJson('/api/v1/entities/me', [])->assertStatus(405)->assertJsonPath('code', 'method_not_allowed');
});

/**
 * @return array<string, Closure(Entity): TestResponse>
 */
function ownProfileWrites(): array
{
    return [
        'identity section' => static fn (Entity $entity): TestResponse => saveSection($entity, ProfileSection::Identity),
        'classification section' => static fn (Entity $entity): TestResponse => saveSection($entity, ProfileSection::Classification),
        'governance section' => static fn (Entity $entity): TestResponse => saveSection($entity, ProfileSection::Governance, ['employees_count' => 3]),
        'contact section' => static fn (Entity $entity): TestResponse => saveSection($entity, ProfileSection::Contact),
        'logo upload' => static fn (Entity $entity): TestResponse => uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes())),
        'logo removal' => static fn (Entity $entity): TestResponse => switchTo(ownerOf($entity))->deleteJson('/api/v1/entities/me/logo'),
        'e-mail change request' => static fn (Entity $entity): TestResponse => switchTo(ownerOf($entity))->postJson('/api/v1/entities/me/email/change', ['new_email' => 'office@alamal.example.sa']),
        'e-mail change confirmation' => static fn (Entity $entity): TestResponse => switchTo(ownerOf($entity))->postJson('/api/v1/entities/me/email/confirm', ['challenge_id' => '9b1e0c4e-2d7a-4f0e-8b3a-5c6d7e8f9a0b', 'code' => '123456']),
        'e-mail change cancellation' => static fn (Entity $entity): TestResponse => switchTo(ownerOf($entity))->postJson('/api/v1/entities/me/email/cancel'),
    ];
}

it('locks every own profile write while the entity is not active', function (string $status, Closure $write): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $status === 'deactivated' ? $factory->deactivated() : $factory->pendingVerification());
    $before = DB::table('entities')->where('id', $entity->id)->first();

    $write($entity)->assertForbidden()->assertJsonPath('code', 'forbidden');

    expect(DB::table('entities')->where('id', $entity->id)->first())->toEqual($before)
        ->and(EntityProfileChange::query()->count())->toBe(0);
})->with(['pending_verification', 'deactivated'])->with(ownProfileWrites());

it('keeps the profile and its history readable while the entity is not active, without edits', function (string $status): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $status === 'deactivated' ? $factory->deactivated() : $factory->pendingVerification());

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.status', $status)
        ->assertJsonPath('data.can_edit', false);
    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/profile-changes')->assertOk();
})->with(['pending_verification', 'deactivated']);

it('does not let a staff member use the own profile writes', function (RoleKey $role, ProfileSection $section): void {
    signIn(staffUser($role));

    $this->patchJson('/api/v1/entities/me/profile/'.$section->value, [])->assertForbidden();
    $this->postJson('/api/v1/entities/me/email/cancel')->assertForbidden();
})->with([RoleKey::SystemAdmin, RoleKey::FinanceOfficer])->with(ProfileSection::cases());

it('answers an unknown section with not found', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->patchJson('/api/v1/entities/me/profile/logo', [])->assertNotFound()->assertJsonPath('code', 'not_found');
});

it('keeps the change history append-only', function (): void {
    $entity = entityWithOwner();
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 99])->assertOk();

    $change = EntityProfileChange::query()->firstOrFail();

    expect(fn () => $change->delete())->toThrow(AppendOnlyViolation::class)
        ->and(fn () => DB::table('entity_profile_changes')->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('entity_profile_changes')->update(['field' => 'name']))->toThrow(QueryException::class);
});
