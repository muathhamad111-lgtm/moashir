<?php

declare(strict_types=1);

use App\Services\Entities\RegionSeedSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * City coordinates place the cities on the executive overview map. Cities installed from the
 * seed file get their coordinates here; cities an administrator adds later stay without a point.
 */
return new class extends Migration
{
    private const string SEED_PATH = 'data/regions/saudi-regions.json';

    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->decimal('latitude', 8, 5)->nullable();
            $table->decimal('longitude', 8, 5)->nullable();
        });

        DB::statement('ALTER TABLE cities ADD CONSTRAINT cities_coordinates_check CHECK ((latitude IS NULL) = (longitude IS NULL) AND (latitude IS NULL OR (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)))');

        $this->fillSeededCoordinates();
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cities DROP CONSTRAINT IF EXISTS cities_coordinates_check');

        Schema::table('cities', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

    private function fillSeededCoordinates(): void
    {
        foreach (app(RegionSeedSource::class)->load(database_path(self::SEED_PATH)) as $region) {
            foreach ($region->cities as $city) {
                if ($city->coordinates === null) {
                    continue;
                }

                DB::table('cities')
                    ->where('code', $city->code)
                    ->whereIn('region_id', DB::table('regions')->where('code', $region->code)->select('id'))
                    ->whereNull('latitude')
                    ->update(['latitude' => $city->coordinates->latitude, 'longitude' => $city->coordinates->longitude]);
            }
        }
    }
};
