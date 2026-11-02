<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\SubCriterionAttributes;
use App\Enums\Criteria\EvaluationType;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Validation\Rule;

final class StoreSubCriterionRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'main_criterion_id' => ['required', 'integer', 'min:1'],
            ...$this->localizedTextRules('name', 'required', self::NAME_MAX),
            ...$this->localizedTextRules('description', 'nullable', self::DESCRIPTION_MAX),
            ...$this->localizedTextRules('assessor_note_label', 'nullable', self::NAME_MAX),
            ...$this->weightRules('nullable'),
            ...$this->attachmentRules(),
            'distribute_equally' => ['sometimes', 'boolean'],
            'evaluation_type' => ['sometimes', Rule::enum(EvaluationType::class)],
            'has_cv' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function mainCriterionId(): int
    {
        return $this->integer('main_criterion_id');
    }

    public function attributesData(): SubCriterionAttributes
    {
        return new SubCriterionAttributes(
            $this->localizedText('name') ?? LocalizedText::fromArray([]),
            $this->localizedText('description'),
            $this->weight(),
            $this->boolean('distribute_equally'),
            $this->enum('evaluation_type', EvaluationType::class) ?? EvaluationType::GuidingPoints,
            $this->boolean('has_cv', true),
            $this->localizedText('assessor_note_label'),
            $this->attachments(),
        );
    }

    public function isActive(): bool
    {
        return $this->boolean('active', true);
    }
}
