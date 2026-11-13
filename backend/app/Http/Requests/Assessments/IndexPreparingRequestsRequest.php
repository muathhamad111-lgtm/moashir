<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Actions\Assessments\ListPreparingRequests;
use App\Data\Assessments\PreparingRequestFilters;
use App\Data\Periods\PeriodFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Services\Assessments\QueuePathSelector;
use App\Support\Periods\PeriodFilterRules;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPreparingRequestsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $followed = array_map(static fn (AssessmentPath $path): string => $path->value, $this->followedPaths());

        return [
            'filter' => ['nullable', 'array:path,cycle_id,period_id'],
            'filter.path' => ['nullable', Rule::in($followed)],
            'filter.cycle_id' => in_array(AssessmentPath::Advanced->value, $followed, true) ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'filter.period_id' => $this->periodRules(),
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(ListPreparingRequests::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): PreparingRequestFilters
    {
        return new PreparingRequestFilters(
            $this->queuePath(),
            $this->filled('filter.cycle_id') ? $this->integer('filter.cycle_id') : null,
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->filled('sort') ? $this->string('sort')->toString() : 'created_at',
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
