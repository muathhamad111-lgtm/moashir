<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityExport;
use App\Models\Entities\Entity;
use App\Support\CsvCell;
use App\Support\ScalarValue;

final class EntityCsvWriter
{
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    private const array COLUMNS = [
        'reference', 'name_ar', 'name_en', 'entity_type', 'development_sector', 'org_size', 'founding_year',
        'region', 'city', 'governance_grade', 'employees_count', 'annual_beneficiaries_count',
        'has_strategic_plan', 'status', 'authorized_person_name', 'official_email', 'registered_at',
    ];

    /**
     * @param  resource  $stream
     */
    public function write(EntityExport $export, $stream): void
    {
        fwrite($stream, self::BYTE_ORDER_MARK);
        $this->writeRow($stream, array_map(
            fn (string $column): string => $this->label('entities.export.columns.'.$column, $export->locale),
            self::COLUMNS,
        ));

        foreach ($export->entities as $entity) {
            $this->writeRow($stream, $this->row($entity, $export->locale));
        }
    }

    /**
     * @return list<string>
     */
    public function row(Entity $entity, string $locale): array
    {
        return [
            $entity->reference(),
            $entity->name->ar,
            $entity->name->en ?? '',
            $this->label('entities.types.'.$entity->entity_type->value, $locale),
            $this->label('entities.sectors.'.$entity->development_sector->value, $locale),
            $this->label('entities.sizes.'.$entity->org_size->value, $locale),
            (string) $entity->founding_year,
            $entity->region->name->in($locale),
            $entity->city->name->in($locale),
            $entity->governance_grade ?? '',
            $entity->employees_count === null ? '' : (string) $entity->employees_count,
            $entity->annual_beneficiaries_count === null ? '' : (string) $entity->annual_beneficiaries_count,
            $entity->has_strategic_plan === null ? '' : $this->label('entities.export.'.($entity->has_strategic_plan ? 'yes' : 'no'), $locale),
            $this->label('entities.statuses.'.$entity->status->value, $locale),
            $entity->owner->name ?? '',
            $entity->owner->email ?? '',
            $entity->created_at->toIso8601String(),
        ];
    }

    public function neutralize(string $cell): string
    {
        return CsvCell::neutralize($cell);
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $cells
     */
    private function writeRow($stream, array $cells): void
    {
        fputcsv($stream, array_map($this->neutralize(...), $cells), ',', '"', '');
    }

    private function label(string $key, string $locale): string
    {
        return ScalarValue::string(__($key, [], $locale));
    }
}
