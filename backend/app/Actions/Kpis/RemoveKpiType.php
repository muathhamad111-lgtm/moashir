<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Kpis\KpiRejected;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiType;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveKpiType
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(KpiType $type): void
    {
        DB::transaction(function () use ($type): void {
            $current = KpiType::query()->lockForUpdate()->findOrFail($type->id);
            $inUse = Kpi::withTrashed()->where('kpi_type_id', $current->id)->count();

            if ($inUse > 0) {
                throw KpiRejected::typeInUse($inUse);
            }

            $current->delete();
            $this->audit->record(new AuditEntry(AuditEvent::KpiTypeRemoved, $current, ['key' => $current->key], []));
        });
    }
}
