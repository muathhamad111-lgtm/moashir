<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Kpis\KpiRejected;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiCategory;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveKpiCategory
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(KpiCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $current = KpiCategory::query()->lockForUpdate()->findOrFail($category->id);
            $inUse = Kpi::query()->where('kpi_category_id', $current->id)->count();

            if ($inUse > 0) {
                throw KpiRejected::categoryInUse($inUse);
            }

            $current->delete();
            $this->audit->record(new AuditEntry(AuditEvent::KpiCategoryRemoved, $current, ['key' => $current->key], []));
        });
    }
}
