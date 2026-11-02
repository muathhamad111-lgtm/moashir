<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\CityData;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\City;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCity
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(City $city, CityData $data): City
    {
        $before = ['name' => $city->name->toArray(), 'sort_order' => $city->sort_order];

        DB::transaction(function () use ($city, $data, $before): void {
            $city->forceFill([
                'name' => $data->name,
                'sort_order' => $data->sortOrder ?? $city->sort_order,
            ])->save();

            $this->audit->record(new AuditEntry(AuditEvent::CityUpdated, $city, $before, [
                'name' => $city->name->toArray(),
                'sort_order' => $city->sort_order,
            ]));
        });

        return $city;
    }
}
