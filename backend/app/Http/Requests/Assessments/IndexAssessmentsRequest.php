<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Actions\Assessments\ListAssessments;
use App\Data\Assessments\AssessmentListFilters;
use App\Enums\Assessments\AssessmentPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAssessmentsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:path,status,entity_id,is_open,has_result'],
            'filter.path' => ['nullable', Rule::enum(AssessmentPath::class)],
            'filter.status' => ['nullable', Rule::in(array_values(array_unique(array_merge(...array_map(
                static fn (AssessmentPath $path): array => $path->stateValues(),
                AssessmentPath::cases(),
            )))))],
            'filter.entity_id' => ['nullable', 'integer', 'min:1'],
            'filter.is_open' => ['nullable', 'in:true,false'],
            'filter.has_result' => ['nullable', 'in:true,false'],
            'sort' => ['nullable', Rule::in(ListAssessments::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): AssessmentListFilters
    {
        $entityId = $this->input('filter.entity_id');
        $status = $this->input('filter.status');

        return new AssessmentListFilters(
            AssessmentPath::tryFrom($this->string('filter.path')->toString()),
            is_string($status) && $status !== '' ? $status : null,
            is_numeric($entityId) ? (int) $entityId : null,
            $this->filled('sort') ? $this->string('sort')->toString() : '-created_at',
            $this->integer('per_page', 15),
            $this->flag('filter.is_open'),
            $this->flag('filter.has_result'),
        );
    }

    private function flag(string $key): ?bool
    {
        return $this->filled($key) ? $this->string($key)->toString() === 'true' : null;
    }
}
