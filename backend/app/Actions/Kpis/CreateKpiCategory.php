<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Data\Kpis\KpiCatalogEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Kpis\KpiCategory;
use App\Services\Audit\AuditRecorder;
use App\Services\Platform\ListOrder;
use Illuminate\Support\Facades\DB;

final readonly class CreateKpiCategory
{
    public function __construct(private ListOrder $order, private AuditRecorder $audit) {}

    public function handle(KpiCatalogEntry $entry): KpiCategory
    {
        return DB::transaction(function () use ($entry): KpiCategory {
            $category = new KpiCategory;
            $category->fill(['sort_order' => $this->order->nextPosition(KpiCategory::query()->max('sort_order')), ...$entry->attributes])->save();
            $this->audit->record(new AuditEntry(AuditEvent::KpiCategoryCreated, $category, [], $category->only(['key', 'sort_order', 'is_active'])));

            return $category;
        });
    }
}
