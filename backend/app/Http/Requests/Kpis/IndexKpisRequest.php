<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Data\Kpis\KpiListFilters;
use App\Enums\Kpis\KpiSort;
use App\Enums\Kpis\KpiStatus;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexKpisRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 12;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:category_id,status,measurement_year,kpi_type_id,owner'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
            'filter.status' => ['nullable', Rule::enum(KpiStatus::class)],
            'filter.measurement_year' => ['nullable', 'integer', 'between:2000,2100'],
            'filter.kpi_type_id' => ['nullable', 'integer', 'min:1'],
            'filter.owner' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'sort' => ['nullable', Rule::in(KpiSort::valuesOf(KpiSort::publicSorts()))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): KpiListFilters
    {
        return new KpiListFilters(
            $this->termOf('search'),
            $this->filled('filter.category_id') ? $this->integer('filter.category_id') : null,
            KpiStatus::tryFrom($this->string('filter.status')->toString()),
            $this->filled('filter.measurement_year') ? $this->integer('filter.measurement_year') : null,
            $this->filled('filter.kpi_type_id') ? $this->integer('filter.kpi_type_id') : null,
            $this->termOf('filter.owner'),
            true,
            null,
            KpiSort::tryFrom($this->string('sort')->toString()) ?? KpiSort::Order,
            $this->integer('per_page', self::DEFAULT_PER_PAGE),
        );
    }

    private function termOf(string $key): ?SearchTerm
    {
        return $this->filled($key) ? SearchTerm::tryFrom($this->string($key)->toString()) : null;
    }
}
