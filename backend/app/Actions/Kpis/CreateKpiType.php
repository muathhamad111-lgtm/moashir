<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Data\Kpis\KpiCatalogEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Kpis\KpiType;
use App\Services\Audit\AuditRecorder;
use App\Services\Platform\ListOrder;
use Illuminate\Support\Facades\DB;

final readonly class CreateKpiType
{
    public function __construct(private ListOrder $order, private AuditRecorder $audit) {}

    public function handle(KpiCatalogEntry $entry): KpiType
    {
        return DB::transaction(function () use ($entry): KpiType {
            $type = new KpiType;
            $type->fill(['sort_order' => $this->order->nextPosition(KpiType::query()->max('sort_order')), ...$entry->attributes])->save();
            $this->audit->record(new AuditEntry(AuditEvent::KpiTypeCreated, $type, [], $type->only(['key', 'sort_order', 'is_active'])));

            return $type;
        });
    }
}
