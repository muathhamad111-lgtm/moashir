<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Enums\Criteria\ScoringType;
use Illuminate\Validation\Rule;

final class ApplyPointScoringTypeRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['scoring_type' => ['required', Rule::enum(ScoringType::class)]];
    }

    public function chosenType(): ScoringType
    {
        return $this->scoringType('scoring_type') ?? ScoringType::Score;
    }
}
