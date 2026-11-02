<?php

declare(strict_types=1);

use App\Console\Commands\InstallRegionsSeedCommand;
use App\Data\Entities\CitySeed;
use App\Data\Entities\EntityProfileData;
use App\Data\Entities\OpenEntityRequest;
use App\Data\Entities\RegionSeed;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityAccess;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Entities\EntityRejected;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Services\Entities\EntityCsvWriter;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Entities\EntityProfileDiff;
use App\Services\Entities\EntityRecordScope;
use App\Services\Entities\OpenRequestDirectory;
use App\Services\Entities\RegionSeedSource;
use App\Support\ValueObjects\EntityName;

it('reports only the changed profile fields with comparable before and after values', function (): void {
    $entity = Entity::factory()->create([
        'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => null],
        'entity_type' => EntityType::CivilAssociation,
        'governance_grade' => '80.00',
        'employees_count' => 10,
    ]);

    $profile = new EntityProfileData(
        new EntityName('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'),
        EntityType::Waqf,
        $entity->development_sector,
        $entity->org_size,
        $entity->founding_year,
        $entity->region_id,
        $entity->city_id,
        '80.00',
        11,
        $entity->annual_beneficiaries_count,
        $entity->has_strategic_plan,
    );

    $changes = app(EntityProfileDiff::class)->between($entity, $profile);

    expect(array_keys($changes->after))->toBe(['name', 'entity_type', 'employees_count'])
        ->and($changes->before['entity_type'])->toBe('civil_association')
        ->and($changes->after['entity_type'])->toBe('waqf')
        ->and($changes->after['name'])->toBe(['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'])
        ->and($changes->isEmpty())->toBeFalse();
});

it('maps roles to the entity record scope of the approved matrix', function (RoleKey $role, EntityAccess $access): void {
    expect(app(EntityRecordScope::class)->accessOf(staffUser($role)))->toBe($access);
})->with([
    [RoleKey::SystemAdmin, EntityAccess::Full],
    [RoleKey::RequestFollower, EntityAccess::Full],
    [RoleKey::FinanceOfficer, EntityAccess::Contact],
    [RoleKey::Assessor, EntityAccess::None],
    [RoleKey::FilteringOfficer, EntityAccess::None],
    [RoleKey::AwardSecretary, EntityAccess::None],
    [RoleKey::CriteriaOfficer, EntityAccess::None],
]);

it('grants the Super Admin full entity access and keeps entity users out', function (): void {
    expect(app(EntityRecordScope::class)->accessOf(superAdmin()))->toBe(EntityAccess::Full)
        ->and(app(EntityRecordScope::class)->accessOf(ownerOf(entityWithOwner())))->toBe(EntityAccess::None);
});

it('freezes only the records of soft-deleted entities and filters them out of work queues', function (): void {
    $operational = Entity::factory()->create();
    $deleted = Entity::factory()->create();
    $deleted->delete();
    EntityProfileChange::query()->create(['entity_id' => $operational->id, 'field' => 'employees_count', 'old_value' => 1, 'new_value' => 2]);
    EntityProfileChange::query()->create(['entity_id' => $deleted->id, 'field' => 'employees_count', 'old_value' => 1, 'new_value' => 2]);
    $guard = app(EntityFreezeGuard::class);

    expect($guard->isFrozen($deleted->id))->toBeTrue()
        ->and($guard->isFrozen($operational->id))->toBeFalse()
        ->and($guard->excludeFrozen(EntityProfileChange::query())->pluck('entity_id')->all())->toBe([$operational->id])
        ->and(fn () => $guard->assertNotFrozen($deleted->refresh()))->toThrow(EntityRejected::class)
        ->and(fn () => $guard->assertEntityNotFrozen($deleted->id))->toThrow(EntityRejected::class);

    $guard->assertNotFrozen($operational);
    $guard->assertEntityNotFrozen($operational->id);
});

it('collects the open requests of every registered source in number order', function (): void {
    $entity = Entity::factory()->create();
    giveOpenRequests($entity, [openRequest('BAG-2026-000003', EntityRequestPath::Bag, 3), openRequest('ACC-2026-000014')]);

    $numbers = array_map(static fn (OpenEntityRequest $request): string => $request->number, app(OpenRequestDirectory::class)->openRequestsOf($entity));

    expect($numbers)->toBe(['ACC-2026-000014', 'BAG-2026-000003'])
        ->and(app(OpenRequestDirectory::class)->openRequestsOf(Entity::factory()->create()))->toBe([]);
});

it('neutralizes spreadsheet formula prefixes in CSV cells', function (string $cell, string $expected): void {
    expect(app(EntityCsvWriter::class)->neutralize($cell))->toBe($expected);
})->with([
    ['=SUM(A1)', "'=SUM(A1)"],
    ['+966551234567', "'+966551234567"],
    ['-5', "'-5"],
    ['@cmd', "'@cmd"],
    ['جمعية الأمل الخيرية', 'جمعية الأمل الخيرية'],
    ['', ''],
]);

it('ships a valid seed of the 13 regions with unique codes and bilingual names', function (): void {
    $regions = app(RegionSeedSource::class)->load(database_path(InstallRegionsSeedCommand::SEED_PATH));
    $codes = array_map(static fn (RegionSeed $region): string => $region->code, $regions);

    expect($regions)->toHaveCount(13)
        ->and(array_unique($codes))->toHaveCount(13);

    foreach ($regions as $region) {
        $cityCodes = array_map(static fn (CitySeed $city): string => $city->code, $region->cities);

        expect($region->cities)->not->toBeEmpty()
            ->and(array_unique($cityCodes))->toHaveCount(count($cityCodes))
            ->and($region->name->en)->not->toBe('')
            ->and(preg_match('/[\x{0660}-\x{0669}]/u', $region->name->ar))->toBe(0);
    }
});

it('rejects a malformed regions seed file', function (string $contents): void {
    expect(fn () => app(RegionSeedSource::class)->load(temporaryListFile($contents)))->toThrow(InvalidArgumentException::class);
})->with([
    'not an object' => ['"regions"'],
    'invalid code' => ['{"regions":[{"code":"Riyadh Region","name":{"ar":"الرياض","en":"Riyadh"},"cities":[]}]}'],
    'missing English name' => ['{"regions":[{"code":"riyadh","name":{"ar":"الرياض"},"cities":[]}]}'],
]);

it('keeps the development sectors and organization sizes of the requirements', function (): void {
    expect(array_column(DevelopmentSector::cases(), 'value'))->toBe(['educational', 'social', 'health', 'developmental'])
        ->and(array_column(OrgSize::cases(), 'value'))->toBe(['small', 'medium', 'large'])
        ->and(array_column(EntityType::cases(), 'value'))->toBe(['civil_association', 'civil_foundation', 'waqf']);
});
