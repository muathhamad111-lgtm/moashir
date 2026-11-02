<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Actions\Award\ListAwardCycles;
use App\Data\Award\CycleListFilters;
use App\Enums\Award\AwardCycleStatus;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAwardCyclesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', Rule::enum(AwardCycleStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(ListAwardCycles::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): CycleListFilters
    {
        return new CycleListFilters(
            AwardCycleStatus::tryFrom($this->string('filter.status')->toString()),
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->filled('sort') ? $this->string('sort')->toString() : '-submission_start',
            $this->integer('per_page', 15),
        );
    }
}
