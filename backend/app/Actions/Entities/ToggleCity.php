<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\City;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ToggleCity
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(City $city, bool $active): City
    {
        if ($city->is_active === $active) {
            return $city;
        }

        DB::transaction(function () use ($city, $active): void {
            $city->forceFill(['is_active' => $active])->save();

            $this->audit->record(new AuditEntry(AuditEvent::CityToggled, $city, ['is_active' => ! $active], ['is_active' => $active]));
        });

        return $city;
    }
}
