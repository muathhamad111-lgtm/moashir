<?php

declare(strict_types=1);

use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Region;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-17 08:00:00');
});

/**
 * @return array{north: Region, south: Region}
 */
function twoFilteredRegions(): array
{
    $north = Region::factory()->create(['code' => 'north', 'sort_order' => 1]);
    $south = Region::factory()->create(['code' => 'south', 'sort_order' => 2]);
    $version = publishedAccreditedCriteria();

    $association = profiledEntity(['entity_type' => EntityType::CivilAssociation, 'development_sector' => DevelopmentSector::Health, 'org_size' => OrgSize::Small], $north);
    storedPaidPayment($association);
    Assessment::factory()->boundTo($version)->forEntity($association)->create();

    for ($index = 0; $index < 2; $index++) {
        $waqf = profiledEntity(['entity_type' => EntityType::Waqf, 'development_sector' => DevelopmentSector::Social, 'org_size' => OrgSize::Large], $south);
        storedPaidPayment($waqf, IntegrationMode::Live, PaymentPurpose::SelfAssessment);
        Assessment::factory()->boundTo($version)->forEntity($waqf)->create();
    }

    return ['north' => $north, 'south' => $south];
}

function filteredOverview(string $query): TestResponse
{
    return overviewOf(systemAdmin(), 'period=month&'.$query);
}

/**
 * @return array{entities: int, collected: string, requests: int, payments: int, regions: list<string>}
 */
function filteredFigures(TestResponse $response): array
{
    $cards = overviewCards($response);

    return [
        'entities' => $cards->get('new_entities')['value'],
        'collected' => $cards->get('payments_collected')['value'],
        'requests' => collect($response->json('data.breakdowns.requests_by_status'))->sum('count'),
        'payments' => collect($response->json('data.breakdowns.payments_by_status'))->sum('count'),
        'regions' => collect($response->json('data.breakdowns.entities_by_region'))->pluck('key')->all(),
    ];
}

it('narrows every figure to the chosen region, type, sector or size', function (string $query, array $expected): void {
    twoFilteredRegions();

    expect(filteredFigures(filteredOverview($query)))->toBe($expected);
})->with([
    'no filter' => ['', ['entities' => 3, 'collected' => '3450.00', 'requests' => 3, 'payments' => 3, 'regions' => ['south', 'north']]],
    'region' => ['region=north', ['entities' => 1, 'collected' => '1150.00', 'requests' => 1, 'payments' => 1, 'regions' => ['north']]],
    'type' => ['type=waqf', ['entities' => 2, 'collected' => '2300.00', 'requests' => 2, 'payments' => 2, 'regions' => ['south']]],
    'sector' => ['sector=health', ['entities' => 1, 'collected' => '1150.00', 'requests' => 1, 'payments' => 1, 'regions' => ['north']]],
    'size' => ['size=large', ['entities' => 2, 'collected' => '2300.00', 'requests' => 2, 'payments' => 2, 'regions' => ['south']]],
    'no match' => ['region=north&type=waqf', ['entities' => 0, 'collected' => '0.00', 'requests' => 0, 'payments' => 0, 'regions' => []]],
]);

it('narrows requests to the chosen path and payments to its purpose', function (string $path, int $requests, string $collected, int $payments): void {
    twoFilteredRegions();
    $response = filteredOverview('path='.$path);

    expect(filteredFigures($response))->toMatchArray(['entities' => 3, 'requests' => $requests, 'collected' => $collected, 'payments' => $payments])
        ->and(collect($response->json('data.paths'))->firstWhere('path', 'self')['completed'])->toBe(0);
})->with([
    'self' => ['self', 3, '2300.00', 2],
    'accredited' => ['accredited', 0, '1150.00', 1],
    'advanced' => ['advanced', 0, '0.00', 0],
]);

it('narrows the map and the snapshot to the chosen dimensions', function (): void {
    twoFilteredRegions();
    $response = filteredOverview('type=waqf');
    $regions = collect($response->json('data.geography.regions'))->keyBy('code');

    expect($regions->get('south')['entities'])->toBe(2)
        ->and($regions->get('north')['entities'])->toBe(0)
        ->and(collect(overviewWidget($response, 'entities_by_sector'))->pluck('count', 'key')->all())->toBe(['social' => 2])
        ->and(overviewCards($response)->get('entities_active')['value'])->toBe(2);
});

it('refuses an unknown filter value', function (string $parameter, string $value): void {
    Region::factory()->create(['code' => 'north']);

    signIn(systemAdmin())->getJson(DASHBOARD_OVERVIEW.'?'.$parameter.'='.$value)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$parameter]);
})->with([
    'region' => ['region', 'atlantis'],
    'path' => ['path', 'premium'],
    'type' => ['type', 'company'],
    'sector' => ['sector', 'mining'],
    'size' => ['size', 'huge'],
]);

it('leaves the assessor overview without the filters', function (): void {
    $assessor = assessorUser();

    switchTo($assessor)->getJson('/api/v1/dashboard/assessor-overview?region=atlantis')->assertOk();
});
