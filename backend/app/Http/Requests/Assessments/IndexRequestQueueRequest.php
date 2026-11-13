<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Actions\Assessments\ListRequestQueue;
use App\Data\Assessments\RequestQueueFilters;
use App\Data\Periods\PeriodFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Services\Assessments\QueuePathSelector;
use App\Support\Periods\PeriodFilterRules;
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
        $followed = array_map(static fn (AssessmentPath $path): string => $path->value, $this->followedPaths());

        return [
            'filter' => ['nullable', 'array:path,status,overdue,assessor_id,cycle_id,period_id'],
            'filter.path' => ['nullable', Rule::in($followed)],
            'filter.status' => ['nullable', Rule::in($this->queuePath()->queueStateValues())],
            'filter.overdue' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'filter.assessor_id' => ['nullable', 'integer', 'min:1'],
            'filter.cycle_id' => in_array(AssessmentPath::Advanced->value, $followed, true) ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'filter.period_id' => $this->periodRules(),
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(ListRequestQueue::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): RequestQueueFilters
    {
        $overdue = $this->input('filter.overdue');

        return new RequestQueueFilters(
            $this->queuePath(),
            $this->filled('filter.status') ? $this->string('filter.status')->toString() : null,
            $overdue === null ? null : in_array($overdue, ['true', '1'], true),
            $this->filled('filter.assessor_id') ? $this->integer('filter.assessor_id') : null,
            $this->filled('filter.cycle_id') ? $this->integer('filter.cycle_id') : null,
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->filled('sort') ? $this->string('sort')->toString() : '-submitted_at',
            $this->integer('per_page', 15),
            PeriodFilter::fromInput($this->input('filter.period_id')),
        );
    }

    /**
     * @return non-empty-list<AssessmentPath>
     */
    public function followedPaths(): array
    {
        return app(QueuePathSelector::class)->followedBy($this->user());
    }

    /**
     * @return list<mixed>
     */
    private function periodRules(): array
    {
        if ($this->queuePath() !== AssessmentPath::Accredited) {
            return ['prohibited'];
        }

        return PeriodFilterRules::for($this->input('filter.period_id'));
    }

    private function queuePath(): AssessmentPath
    {
        if ($this->filled('filter.cycle_id')) {
            return AssessmentPath::Advanced;
        }

        return app(QueuePathSelector::class)->chosenBy($this->user(), $this->string('filter.path')->toString());
    }
}
