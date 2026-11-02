<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Enums\Assessments\AssignedRequestsTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAssignedRequestsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:tab'],
            'filter.tab' => ['nullable', Rule::enum(AssignedRequestsTab::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function tab(): ?AssignedRequestsTab
    {
        return AssignedRequestsTab::tryFrom($this->string('filter.tab')->toString());
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
