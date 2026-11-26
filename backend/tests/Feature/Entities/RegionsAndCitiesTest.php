<?php

declare(strict_types=1);

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

// Operator note 2026-11-22: the cities screen left the settings; the list lives in code and the seed only.
it('no longer offers listing, adding, editing or switching cities because the list is fixed', function (): void {
    $city = regionWithCity();
    signIn(systemAdmin());
    $citiesBefore = City::query()->count();

    $this->getJson('/api/v1/cities')->assertNotFound();
    $this->patchJson("/api/v1/cities/{$city->id}/active", ['active' => false])->assertNotFound();
    $this->postJson("/api/v1/regions/{$city->region_id}/cities", ['name' => ['ar' => 'الدلم', 'en' => 'Ad Dilam']])->assertNotFound();
    $this->putJson("/api/v1/cities/{$city->id}", ['name' => ['ar' => 'مدينة الرياض', 'en' => 'Riyadh City']])->assertNotFound();

    expect(City::query()->count())->toBe($citiesBefore)
        ->and($city->refresh()->is_active)->toBeTrue()
        ->and($city->refresh()->name->en)->toBe('Riyadh');
});
