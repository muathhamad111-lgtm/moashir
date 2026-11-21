<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\CommitteeAssignmentFilters;
use App\Enums\Award\CommitteeAssignmentsSort;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Support\ListOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexMyCommitteeAssignmentsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:cycle_id,status'],
            'filter.cycle_id' => ['nullable', 'integer', 'min:1'],
            'filter.status' => ['nullable', Rule::in([CommitteeAssignmentStatus::NotStarted->value, CommitteeAssignmentStatus::InProgress->value, CommitteeAssignmentStatus::Completed->value])],
            'sort' => ['nullable', 'string', Rule::in(ListOrder::queryValues(array_column(CommitteeAssignmentsSort::cases(), 'value')))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): CommitteeAssignmentFilters
    {
        return new CommitteeAssignmentFilters(
            $this->filled('filter.cycle_id') ? $this->integer('filter.cycle_id') : null,
            CommitteeAssignmentStatus::tryFrom($this->string('filter.status')->toString()),
            ListOrder::fromQuery($this->string('sort')->toString(), CommitteeAssignmentsSort::DueAt->value),
            $this->integer('per_page', 15),
        );
    }
}
