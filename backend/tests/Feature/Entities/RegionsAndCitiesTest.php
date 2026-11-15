<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Entities\City;
use App\Models\Entities\Region;

it('installs the 13 administrative regions with their cities idempotently', function (): void {
    $this->artisan('moashir:regions:install-seed')->expectsOutputToContain('Regions added: 13.')->assertSuccessful();

    $riyadh = Region::query()->where('code', 'riyadh')->firstOrFail();
    $riyadh->forceFill(['name' => ['ar' => 'منطقة الرياض الإدارية', 'en' => 'Riyadh Administrative Region']])->save();
    $citiesBefore = City::query()->count();

    $this->artisan('moashir:regions:install-seed')->expectsOutputToContain('Regions added: 0. Cities added: 0.')->assertSuccessful();

    expect(Region::query()->count())->toBe(13)
        ->and(Region::query()->pluck('code')->all())->toContain('riyadh', 'makkah', 'madinah', 'eastern', 'qassim', 'hail', 'tabuk', 'northern_borders', 'jazan', 'najran', 'bahah', 'jouf', 'asir')
        ->and(City::query()->count())->toBe($citiesBefore)
        ->and($citiesBefore)->toBeGreaterThan(60)
        ->and($riyadh->refresh()->name->en)->toBe('Riyadh Administrative Region');
});

it('serves the active regions and their active cities to guests', function (): void {
    $city = regionWithCity();
    City::factory()->inactive()->create(['region_id' => $city->region_id, 'name' => ['ar' => 'مدينة موقوفة', 'en' => 'Retired city']]);
    Region::factory()->inactive()->create();

    $this->getJson('/api/v1/regions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $city->region_id)
        ->assertJsonCount(1, 'data.0.cities')
        ->assertJsonPath('data.0.cities.0', ['id' => $city->id, 'code' => $city->code, 'name' => ['ar' => 'الرياض', 'en' => 'Riyadh']]);
});

it('deactivates and reactivates a city', function (): void {
    $city = regionWithCity();
    signIn(systemAdmin());

    $this->patchJson("/api/v1/cities/{$city->id}/active", ['active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    $this->getJson('/api/v1/cities?filter[active]=false')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/regions')->assertOk()->assertJsonCount(0, 'data.0.cities');

    $this->patchJson("/api/v1/cities/{$city->id}/active", ['active' => true])->assertOk()->assertJsonPath('data.is_active', true);

    expect(Audit::query()->where('event', AuditEvent::CityToggled->value)->count())->toBe(2)
        ->and($city->refresh()->name->en)->toBe('Riyadh');
});

it('validates the city active toggle', function (): void {
    $city = regionWithCity();
    signIn(systemAdmin());

    $this->patchJson("/api/v1/cities/{$city->id}/active", ['active' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors(['active']);
    $this->patchJson("/api/v1/cities/{$city->id}/active", [])->assertUnprocessable()->assertJsonValidationErrors(['active']);
});

it('no longer offers adding or editing cities because the list is fixed', function (): void {
    $city = regionWithCity();
    signIn(systemAdmin());
    $citiesBefore = City::query()->count();

    $this->postJson("/api/v1/regions/{$city->region_id}/cities", ['name' => ['ar' => 'الدلم', 'en' => 'Ad Dilam']])->assertNotFound();
    $this->putJson("/api/v1/cities/{$city->id}", ['name' => ['ar' => 'مدينة الرياض', 'en' => 'Riyadh City']])->assertNotFound();

    expect(City::query()->count())->toBe($citiesBefore)
        ->and($city->refresh()->name->en)->toBe('Riyadh');
});

it('lists the cities for administration with search and region filters', function (): void {
    $city = regionWithCity();
    regionWithCity()->forceFill(['name' => ['ar' => 'أبها', 'en' => 'Abha']])->save();
    signIn(systemAdmin());

    $this->getJson('/api/v1/cities?filter[region_id]='.$city->region_id)->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/cities?search='.urlencode('ابها'))->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.name.en', 'Abha');
});

it('keeps the city list management to holders of the manage permission', function (RoleKey $role): void {
    $city = regionWithCity();
    signIn(staffUser($role));

    $this->getJson('/api/v1/cities')->assertForbidden();
    $this->patchJson("/api/v1/cities/{$city->id}/active", ['active' => false])->assertForbidden();
})->with([RoleKey::RequestFollower, RoleKey::ContentOfficer, RoleKey::FinanceOfficer]);

it('answers 404 when toggling an unknown city', function (): void {
    signIn(systemAdmin());

    $this->patchJson('/api/v1/cities/999999/active', ['active' => false])->assertNotFound();
});
