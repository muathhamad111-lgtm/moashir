<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Data\Kpis\KpiCatalogEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Kpis\KpiCategory;
use App\Services\Audit\AuditRecorder;

final readonly class UpdateKpiCategory
{
    private const array AUDITED = ['key', 'sort_order', 'is_active', 'icon'];

    public function __construct(private AuditRecorder $audit) {}

    public function handle(KpiCategory $category, KpiCatalogEntry $entry): void
    {
        $before = [...$category->only(self::AUDITED), 'name' => $category->name->toArray()];
        $category->fill($entry->attributes);

        if (! $category->isDirty()) {
            return;
        }

        $category->save();
        $this->audit->record(new AuditEntry(AuditEvent::KpiCategoryUpdated, $category, $before, [...$category->only(self::AUDITED), 'name' => $category->name->toArray()]));
    }
}
