<?php

declare(strict_types=1);

namespace App\Http\Requests\Periods;

use App\Data\Periods\PeriodListFilters;
use App\Enums\Periods\PeriodTimeline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAssessmentPeriodsRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 20;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:state'],
            'filter.state' => ['nullable', Rule::enum(PeriodTimeline::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): PeriodListFilters
    {
        return new PeriodListFilters($this->enum('filter.state', PeriodTimeline::class), $this->integer('per_page', self::DEFAULT_PER_PAGE));
    }
}
