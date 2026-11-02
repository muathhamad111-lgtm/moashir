<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Actions\Entities\ListEntities;
use App\Data\Entities\EntityListFilters;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityStatus;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Models\Entities\Entity;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexEntitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->wantsDeletedEntities() || $this->user()?->can('viewDeleted', Entity::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array:status,region_id,city_id,entity_type,development_sector,org_size,trashed'],
            'filter.status' => ['nullable', Rule::enum(EntityStatus::class)],
            'filter.region_id' => ['nullable', 'integer', 'min:1'],
            'filter.city_id' => ['nullable', 'integer', 'min:1'],
            'filter.entity_type' => ['nullable', Rule::enum(EntityType::class)],
            'filter.development_sector' => ['nullable', Rule::enum(DevelopmentSector::class)],
            'filter.org_size' => ['nullable', Rule::enum(OrgSize::class)],
            'filter.trashed' => ['nullable', Rule::in(['only'])],
            'sort' => ['nullable', Rule::in(ListEntities::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): EntityListFilters
    {
        return new EntityListFilters(
            SearchTerm::tryFrom($this->string('search')->toString()),
            EntityStatus::tryFrom($this->filterValue('status')),
            $this->filterInteger('region_id'),
            $this->filterInteger('city_id'),
            EntityType::tryFrom($this->filterValue('entity_type')),
            DevelopmentSector::tryFrom($this->filterValue('development_sector')),
            OrgSize::tryFrom($this->filterValue('org_size')),
            $this->wantsDeletedEntities(),
            $this->filled('sort') ? $this->string('sort')->toString() : '-created_at',
            $this->integer('per_page', 15),
        );
    }

    private function wantsDeletedEntities(): bool
    {
        return $this->filterValue('trashed') === 'only';
    }

    private function filterValue(string $key): string
    {
        $value = $this->input('filter.'.$key);

        return is_string($value) ? $value : '';
    }

    private function filterInteger(string $key): ?int
    {
        $value = $this->input('filter.'.$key);

        return is_numeric($value) ? (int) $value : null;
    }
}
