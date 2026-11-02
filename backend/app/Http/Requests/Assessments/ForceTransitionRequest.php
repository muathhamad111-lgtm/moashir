<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ForceTransitionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', Rule::in(AccreditedAssessmentStatus::forceTargetValues())],
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function target(): AccreditedAssessmentStatus
    {
        return AccreditedAssessmentStatus::from($this->string('to_status')->toString());
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
