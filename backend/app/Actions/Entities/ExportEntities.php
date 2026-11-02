<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityExport;
use App\Data\Entities\EntityListFilters;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Entities\EntityRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntitySettings;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Support\Facades\Date;

final readonly class ExportEntities
{
    public function __construct(
        private ListEntities $listEntities,
        private EntitySettings $settings,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, EntityListFilters $filters, string $locale): EntityExport
    {
        $query = $this->listEntities->filteredQuery($actor, $filters);
        $rowCount = (clone $query)->count();

        if ($rowCount > $this->settings->exportMaxRows()) {
            throw EntityRejected::exportTooLarge($this->settings->exportMaxRows());
        }

        $this->audit->record(new AuditEntry(AuditEvent::EntitiesExported, $actor, [], [
            'row_count' => $rowCount,
            'filters' => $this->describe($filters),
        ], ['export']));

        return new EntityExport(
            'entities-'.Date::now()->format('Ymd-His').'.csv',
            $rowCount,
            $query->with(['owner', 'region', 'city'])->lazyById(500),
            $locale,
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function describe(EntityListFilters $filters): array
    {
        return [
            'search' => $filters->search instanceof SearchTerm,
            'status' => $filters->status?->value,
            'region_id' => $filters->regionId,
            'city_id' => $filters->cityId,
            'entity_type' => $filters->entityType?->value,
            'development_sector' => $filters->developmentSector?->value,
            'org_size' => $filters->orgSize?->value,
            'only_deleted' => $filters->onlyDeleted,
        ];
    }
}
