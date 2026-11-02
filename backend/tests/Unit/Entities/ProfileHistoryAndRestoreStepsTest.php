<?php

declare(strict_types=1);

use App\Contracts\Entities\EntityRestoreStep;
use App\Data\Entities\OpenEntityRequest;
use App\Data\Entities\ProfileChangePlaces;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Identity\ActorKind;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Services\Entities\EntityRestoreSteps;
use App\Services\Entities\ProfileChangePlacesLoader;
use App\Support\Entities\EntityPresenter;
use Illuminate\Support\Facades\DB;

it('tells who changed a profile field without naming anyone', function (?int $changedBy, ?int $owner, ActorKind $expected): void {
    expect(ActorKind::of($changedBy, $owner))->toBe($expected);
})->with([
    'the entity owner' => [14, 14, ActorKind::Entity],
    'a staff member' => [3, 14, ActorKind::Staff],
    'a staff member of an entity without owner' => [3, null, ActorKind::Staff],
    'no actor' => [null, 14, ActorKind::System],
]);

it('presents region and city values with their names and keeps every other value as recorded', function (): void {
    $region = ['id' => 4, 'code' => 'makkah', 'name' => ['ar' => 'منطقة مكة المكرمة', 'en' => 'Makkah Region']];
    $places = new ProfileChangePlaces([4 => $region], []);

    expect($places->present(EntityProfileField::Region, 4))->toBe($region)
        ->and($places->present(EntityProfileField::City, 9))->toBe(['id' => 9, 'code' => null, 'name' => null])
        ->and($places->present(EntityProfileField::Region, null))->toBeNull()
        ->and($places->present(EntityProfileField::EmployeesCount, 4))->toBe(4)
        ->and($places->present(EntityProfileField::GovernanceGrade, '87.45'))->toBe('87.45');
});

it('loads the places referenced by a page of changes in at most two queries', function (): void {
    $entity = Entity::factory()->create();
    $city = City::factory()->create(['region_id' => $entity->region_id]);
    $changes = [
        EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'region_id', 'old_value' => null, 'new_value' => $entity->region_id]),
        EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'city_id', 'old_value' => $entity->city_id, 'new_value' => $city->id]),
        EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'founding_year', 'old_value' => 2001, 'new_value' => 2002]),
    ];

    DB::enableQueryLog();
    $places = app(ProfileChangePlacesLoader::class)->for($changes);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(2)
        ->and($places->present(EntityProfileField::City, $city->id))->toBe(EntityPresenter::place($city))
        ->and($places->present(EntityProfileField::Region, $entity->region_id))->toBe(EntityPresenter::place($entity->region));
});

it('queries nothing when a page of changes references no place', function (): void {
    $entity = Entity::factory()->create();
    $change = EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'employees_count', 'old_value' => 1, 'new_value' => 2]);

    DB::enableQueryLog();
    $places = app(ProfileChangePlacesLoader::class)->for([$change]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0)
        ->and($places->regions)->toBe([])
        ->and($places->cities)->toBe([]);
});

it('marks an open request as frozen without changing anything else', function (): void {
    $open = new OpenEntityRequest(7, 'SLF-2026-000007', EntityRequestPath::SelfAssessment, 'draft');
    $frozen = $open->frozenWith(true);

    expect($open->isFrozen)->toBeFalse()
        ->and($frozen->toArray())->toBe([
            'id' => 7, 'number' => 'SLF-2026-000007', 'path' => 'self', 'status' => 'draft', 'cycle_id' => null, 'is_frozen' => true,
        ]);
});

it('runs every tagged restore step for the restored entity', function (): void {
    $entity = Entity::factory()->create();
    $step = new class implements EntityRestoreStep
    {
        /**
         * @var list<int>
         */
        public array $restored = [];

        public function onRestore(Entity $entity): void
        {
            $this->restored[] = $entity->id;
        }
    };

    new EntityRestoreSteps([$step, $step])->runFor($entity);

    expect($step->restored)->toBe([$entity->id, $entity->id]);
});
