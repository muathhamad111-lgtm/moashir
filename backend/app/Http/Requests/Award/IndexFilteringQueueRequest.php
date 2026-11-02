<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Actions\Award\ListFilteringQueue;
use App\Data\Award\FilteringQueueFilters;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexFilteringQueueRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status,region_id'],
            'filter.status' => ['nullable', Rule::in(AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::filteringStates()))],
            'filter.region_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(ListFilteringQueue::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): FilteringQueueFilters
    {
        return new FilteringQueueFilters(
            AdvancedAssessmentStatus::tryFrom($this->string('filter.status')->toString()),
            $this->filled('filter.region_id') ? $this->integer('filter.region_id') : null,
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->filled('sort') ? $this->string('sort')->toString() : '-initial_percent',
            $this->integer('per_page', 15),
        );
    }
}
