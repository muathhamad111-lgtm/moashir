<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Geography;

use App\Data\Dashboards\ReportingPeriod;
use App\Models\Entities\City;
use App\Models\Entities\Region;

/**
 * The overview map: each region and city with its registered entities (now) and, inside the
 * period, the requests submitted, the accredited average and the money collected.
 *
 * @phpstan-type PlaceFigures array{entities: int, requests: int, average_percent: string|null, collected: string}
 * @phpstan-type RegionRow array{region_id: int, code: string, name: array<string, string>, entities: int, requests: int, average_percent: string|null, collected: string}
 * @phpstan-type CityRow array{city_id: int, region_id: int, code: string, name: array<string, string>, latitude: float|null, longitude: float|null, entities: int, requests: int, average_percent: string|null, collected: string}
 */
final readonly class GeographicActivity
{
    private const string MONEY_ZERO = '0.00';

    public function __construct(private LocationTotals $totals) {}

    /**
     * @return array{regions: list<RegionRow>, cities: list<CityRow>}
     */
    public function of(ReportingPeriod $period): array
    {
        $figures = [
            'entities' => $this->totals->entities(),
            'requests' => $this->totals->requests($period),
            'average_percent' => $this->totals->averagePercents($period),
            'collected' => $this->totals->collected($period),
        ];

        return [
            'regions' => array_values(Region::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'code', 'name'])
                ->map(fn (Region $region): array => [
                    'region_id' => $region->id,
                    'code' => $region->code,
                    'name' => $region->name->toArray(),
                    ...$this->figuresAt($figures, LocationTotals::regionKey($region->id)),
                ])->all()),
            'cities' => array_values(City::query()->orderBy('region_id')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'region_id', 'code', 'name', 'latitude', 'longitude'])
                ->map(fn (City $city): array => [
                    'city_id' => $city->id,
                    'region_id' => $city->region_id,
                    'code' => $city->code,
                    'name' => $city->name->toArray(),
                    'latitude' => $city->latitude,
                    'longitude' => $city->longitude,
                    ...$this->figuresAt($figures, LocationTotals::cityKey($city->id)),
                ])->all()),
        ];
    }

    /**
     * @param  array{entities: array<string, int>, requests: array<string, int>, average_percent: array<string, string>, collected: array<string, string>}  $figures
     * @return PlaceFigures
     */
    private function figuresAt(array $figures, string $key): array
    {
        return [
            'entities' => $figures['entities'][$key] ?? 0,
            'requests' => $figures['requests'][$key] ?? 0,
            'average_percent' => $figures['average_percent'][$key] ?? null,
            'collected' => $figures['collected'][$key] ?? self::MONEY_ZERO,
        ];
    }
}
