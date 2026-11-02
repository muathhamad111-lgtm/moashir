<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\CityListFilters;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexCitiesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array:region_id,active'],
            'filter.region_id' => ['nullable', 'integer', 'min:1'],
            'filter.active' => ['nullable', Rule::in(['true', 'false'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): CityListFilters
    {
        $regionId = $this->input('filter.region_id');
        $active = $this->input('filter.active');

        return new CityListFilters(
            is_numeric($regionId) ? (int) $regionId : null,
            is_string($active) ? $active === 'true' : null,
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->integer('per_page', 50),
        );
    }
}
