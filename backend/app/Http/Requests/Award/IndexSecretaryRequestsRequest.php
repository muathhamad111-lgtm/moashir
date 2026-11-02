<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\SecretaryRequestFilters;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Services\Award\SecretaryRequestsQuery;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexSecretaryRequestsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status,region_id'],
            'filter.status' => ['nullable', Rule::in(AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::committeeStates(), ...AdvancedAssessmentStatus::resultStates()))],
            'filter.region_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(SecretaryRequestsQuery::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): SecretaryRequestFilters
    {
        return new SecretaryRequestFilters(
            AdvancedAssessmentStatus::tryFrom($this->string('filter.status')->toString()),
            $this->filled('filter.region_id') ? $this->integer('filter.region_id') : null,
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->filled('sort') ? $this->string('sort')->toString() : '-submitted_at',
            $this->integer('per_page', 15),
        );
    }
}
