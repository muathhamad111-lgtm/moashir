<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\SubmittedScores;
use App\Support\Assessments\ScoreInputRules;
use Illuminate\Foundation\Http\FormRequest;

final class SaveSelfAssessmentDraftRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ScoreInputRules::for($this->route('assessment'), 'present');
    }

    public function scores(): SubmittedScores
    {
        $scores = $this->validated('scores');

        return ScoreInputRules::submitted(is_array($scores) ? $scores : []);
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
