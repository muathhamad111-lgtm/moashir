<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Data\Kpis\KpiListFilters;
use App\Enums\Kpis\KpiSort;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAdminKpisRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:published,featured,category_id'],
            'filter.published' => ['nullable', 'in:true,false'],
            'filter.featured' => ['nullable', 'in:true,false'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(KpiSort::valuesOf(KpiSort::adminSorts()))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): KpiListFilters
    {
        return new KpiListFilters(
            $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            $this->filled('filter.category_id') ? $this->integer('filter.category_id') : null,
            null,
            null,
            null,
            null,
            $this->flag('filter.published'),
            $this->flag('filter.featured'),
            KpiSort::tryFrom($this->string('sort')->toString()) ?? KpiSort::Order,
            $this->integer('per_page', self::DEFAULT_PER_PAGE),
        );
    }

    private function flag(string $key): ?bool
    {
        return $this->filled($key) ? $this->string($key)->toString() === 'true' : null;
    }
}
