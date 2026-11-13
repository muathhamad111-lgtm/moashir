<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignedRequestsTab;
use App\Enums\Settings\PathSetting;
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
            'filter' => ['nullable', 'array:tab,path'],
            'filter.tab' => ['nullable', Rule::enum(AssignedRequestsTab::class)],
            'filter.path' => ['nullable', Rule::in(PathSetting::pathValues())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function tab(): ?AssignedRequestsTab
    {
        return AssignedRequestsTab::tryFrom($this->string('filter.tab')->toString());
    }

    public function assessmentPath(): ?AssessmentPath
    {
        return AssessmentPath::tryFrom($this->string('filter.path')->toString());
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
