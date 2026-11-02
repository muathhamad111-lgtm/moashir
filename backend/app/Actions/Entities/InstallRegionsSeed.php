<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\RegionInstallResult;
use App\Data\Entities\RegionSeed;
use App\Models\Entities\City;
use App\Models\Entities\Region;
use App\Services\Entities\RegionSeedSource;
use Illuminate\Support\Facades\DB;

final readonly class InstallRegionsSeed
{
    public function __construct(private RegionSeedSource $source) {}

    public function handle(string $path): RegionInstallResult
    {
        $seeds = $this->source->load($path);

        return DB::transaction(function () use ($seeds): RegionInstallResult {
            $regionsAdded = 0;
            $citiesAdded = 0;

            foreach ($seeds as $seed) {
                $region = Region::query()->firstOrCreate(['code' => $seed->code], ['name' => $seed->name, 'sort_order' => $seed->sortOrder]);
                $regionsAdded += $region->wasRecentlyCreated ? 1 : 0;
                $citiesAdded += $this->installCities($region, $seed);
            }

            return new RegionInstallResult($regionsAdded, $citiesAdded);
        });
    }

    private function installCities(Region $region, RegionSeed $seed): int
    {
        $added = 0;

        foreach ($seed->cities as $citySeed) {
            $city = City::query()->firstOrCreate(
                ['region_id' => $region->id, 'code' => $citySeed->code],
                ['name' => $citySeed->name, 'sort_order' => $citySeed->sortOrder],
            );
            $added += $city->wasRecentlyCreated ? 1 : 0;
        }

        return $added;
    }
}
