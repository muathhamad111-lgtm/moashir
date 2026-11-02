<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\SubmittedScores;
use App\Support\Assessments\ScoreInputRules;
use Illuminate\Foundation\Http\FormRequest;

final class OverrideScoresRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ScoreInputRules::for($this->route('assessment'), 'required');
        $rules['scores.subs.*'] = ['required', ...array_values(array_diff($rules['scores.subs.*'], ['nullable']))];
        $rules['scores.points.*'] = ['required', ...array_values(array_diff($rules['scores.points.*'], ['nullable']))];
        $rules['reason'] = ['required', 'string', 'min:20', 'max:1000'];

        return $rules;
    }

    public function scores(): SubmittedScores
    {
        $scores = $this->validated('scores');

        return ScoreInputRules::submitted(is_array($scores) ? $scores : []);
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
