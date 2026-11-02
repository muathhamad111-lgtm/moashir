<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Actions\Criteria\ListMainCriteria;
use App\Data\Criteria\MainCriteriaListQuery;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexMainCriteriaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array:active'],
            'filter.active' => ['nullable', Rule::in(['true', 'false'])],
            'sort' => ['nullable', Rule::in(ListMainCriteria::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function listQuery(): MainCriteriaListQuery
    {
        $active = $this->input('filter.active');

        return new MainCriteriaListQuery(
            $this->filled('version') ? $this->integer('version') : null,
            SearchTerm::tryFrom($this->string('search')->toString()),
            is_string($active) ? $active === 'true' : null,
            $this->filled('sort') ? $this->string('sort')->toString() : 'order',
            $this->integer('per_page', 50),
        );
    }
}
