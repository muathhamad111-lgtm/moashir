<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Actions\Assessments\ListRequestQueue;
use App\Data\Assessments\RequestQueueFilters;
use App\Enums\Assessments\AssessmentPath;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexRequestQueueRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $path = AssessmentPath::tryFrom($this->string('filter.path')->toString());
        $paths = $path instanceof AssessmentPath && $path->isReviewed() ? [$path] : AssessmentPath::reviewed();

        return [
            'filter' => ['nullable', 'array:path,status,overdue,assessor_id,cycle_id'],
            'filter.path' => ['nullable', Rule::in(array_map(static fn (AssessmentPath $reviewed): string => $reviewed->value, AssessmentPath::reviewed()))],
            'filter.status' => ['nullable', Rule::in(array_merge(...array_map(static fn (AssessmentPath $reviewed): array => $reviewed->queueStateValues(), $paths)))],
            'filter.overdue' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'filter.assessor_id' => ['nullable', 'integer', 'min:1'],
            'filter.cycle_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(ListRequestQueue::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): RequestQueueFilters
    {
        $overdue = $this->input('filter.overdue');
        $cycleId = $this->filled('filter.cycle_id') ? $this->integer('filter.cycle_id') : null;

        return new RequestQueueFilters(
            $cycleId !== null ? AssessmentPath::Advanced : AssessmentPath::tryFrom($this->string('filter.path')->toString()),
            $this->filled('filter.status') ? $this->string('filter.status')->toString() : null,
            $overdue === null ? null : in_array($overdue, ['true', '1'], true),
            $this->filled('filter.assessor_id') ? $this->integer('filter.assessor_id') : null,
            $cycleId,
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->filled('sort') ? $this->string('sort')->toString() : '-submitted_at',
            $this->integer('per_page', 15),
        );
    }
}
