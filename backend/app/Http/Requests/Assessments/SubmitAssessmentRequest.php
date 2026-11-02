<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\SubmittedScores;
use App\Support\Assessments\ScoreInputRules;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitAssessmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ScoreInputRules::for($this->route('assessment'), 'sometimes');
    }

    public function scores(): ?SubmittedScores
    {
        $scores = $this->validated('scores');

        return is_array($scores) ? ScoreInputRules::submitted($scores) : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
