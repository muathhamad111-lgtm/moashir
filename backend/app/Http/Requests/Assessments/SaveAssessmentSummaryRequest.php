<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

final class SaveAssessmentSummaryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'summary' => ['present', 'nullable', 'string', 'max:'.config()->integer('moashir.assessment.summary_max_chars')],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function summary(): ?string
    {
        return $this->filled('summary') ? $this->string('summary')->toString() : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
