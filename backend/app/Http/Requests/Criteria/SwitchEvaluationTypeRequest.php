<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Enums\Criteria\EvaluationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SwitchEvaluationTypeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evaluation_type' => ['required', Rule::enum(EvaluationType::class)],
            'confirm_delete_points' => ['sometimes', 'boolean'],
        ];
    }

    public function evaluationType(): EvaluationType
    {
        return $this->enum('evaluation_type', EvaluationType::class) ?? EvaluationType::GuidingPoints;
    }

    public function confirmsPointDeletion(): bool
    {
        return $this->boolean('confirm_delete_points');
    }
}
