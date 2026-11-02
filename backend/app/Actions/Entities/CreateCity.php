<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\CityData;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\City;
use App\Models\Entities\Region;
use App\Services\Audit\AuditRecorder;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateCity
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Region $region, CityData $data): City
    {
        return DB::transaction(function () use ($region, $data): City {
            Region::query()->whereKey($region->id)->lockForUpdate()->firstOrFail();

            $city = City::query()->create([
                'region_id' => $region->id,
                'code' => $data->code ?? $this->generatedCode($region, $data),
                'name' => $data->name,
                'sort_order' => $data->sortOrder ?? $this->nextSortOrder($region),
                'is_active' => true,
            ]);

            $this->audit->record(new AuditEntry(AuditEvent::CityCreated, $city, [], [
                'region_id' => $region->id,
                'code' => $city->code,
                'name' => $city->name->toArray(),
            ]));

            return $city;
        });
    }

    private function generatedCode(Region $region, CityData $data): string
    {
        $base = Str::limit(Str::slug($data->name->en, '_'), 30, '');
        $base = $base === '' ? 'city' : $base;
        $code = $base;
        $suffix = 2;

        while (City::query()->where('region_id', $region->id)->where('code', $code)->exists()) {
            $code = $base.'_'.$suffix++;
        }

        return $code;
    }

    private function nextSortOrder(Region $region): int
    {
        return ScalarValue::int(City::query()->where('region_id', $region->id)->max('sort_order')) + 1;
    }
}
