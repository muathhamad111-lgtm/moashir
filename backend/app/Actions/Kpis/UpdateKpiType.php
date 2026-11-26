<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Audit\AuditEntry;
use App\Data\Kpis\KpiCatalogEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Kpis\KpiType;
use App\Services\Audit\AuditRecorder;

final readonly class UpdateKpiType
{
    private const array AUDITED = ['key', 'sort_order', 'is_active'];

    public function __construct(private AuditRecorder $audit) {}

    public function handle(KpiType $type, KpiCatalogEntry $entry): void
    {
        $before = [...$type->only(self::AUDITED), 'name' => $type->name->toArray()];
        $type->fill($entry->attributes);

        if (! $type->isDirty()) {
            return;
        }

        $type->save();
        $this->audit->record(new AuditEntry(AuditEvent::KpiTypeUpdated, $type, $before, [...$type->only(self::AUDITED), 'name' => $type->name->toArray()]));
    }
}
