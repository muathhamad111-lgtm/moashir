<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\ProfileChangeListFilters;
use App\Enums\Entities\ProfileChangeStatus;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexProfileChangeRequestsRequest extends FormRequest
{
    private const string ALL = 'all';

    private const int PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status,entity_id'],
            'filter.status' => ['nullable', Rule::in([...ProfileChangeStatus::values(), self::ALL])],
            'filter.entity_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): ProfileChangeListFilters
    {
        $status = $this->input('filter.status');
        $entityId = $this->input('filter.entity_id');

        return new ProfileChangeListFilters(
            is_string($status) ? ProfileChangeStatus::tryFrom($status) : ProfileChangeStatus::Pending,
            is_numeric($entityId) ? (int) $entityId : null,
            $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            $this->integer('per_page', self::PER_PAGE),
        );
    }
}
