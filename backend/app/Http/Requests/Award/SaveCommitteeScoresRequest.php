<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Assessments\SubmittedScores;
use App\Models\Award\CommitteeAssignment;
use App\Support\Assessments\ScoreInputRules;
use Illuminate\Foundation\Http\FormRequest;

final class SaveCommitteeScoresRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $assignment = $this->route('assignment');

        return ScoreInputRules::for($assignment instanceof CommitteeAssignment ? $assignment->loadMissing('assessment')->assessment : null, 'required');
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
