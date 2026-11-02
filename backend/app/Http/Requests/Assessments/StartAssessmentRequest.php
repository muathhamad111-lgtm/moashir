<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Enums\Assessments\AssessmentPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartAssessmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['path' => ['required', 'string', Rule::in([AssessmentPath::SelfAssessment->value])]];
    }
}
