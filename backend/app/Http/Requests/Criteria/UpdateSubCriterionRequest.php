<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\SubCriterionAttributes;
use App\Models\Criteria\SubCriterion;

final class UpdateSubCriterionRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->localizedTextRules('name', 'sometimes', self::NAME_MAX),
            ...$this->localizedTextRules('description', 'nullable', self::DESCRIPTION_MAX),
            ...$this->localizedTextRules('assessor_note_label', 'nullable', self::NAME_MAX),
            ...$this->weightRules('sometimes'),
            ...$this->attachmentRules(),
            'distribute_equally' => ['sometimes', 'boolean'],
            'has_cv' => ['sometimes', 'boolean'],
            'evaluation_type' => ['prohibited'],
            'active' => ['prohibited'],
        ];
    }

    public function attributesData(SubCriterion $current): SubCriterionAttributes
    {
        return new SubCriterionAttributes(
            $this->localizedText('name') ?? $current->name,
            $this->has('description') ? $this->localizedText('description') : $current->description,
            $this->weight() ?? $current->weight,
            $this->has('distribute_equally') ? $this->boolean('distribute_equally') : $current->distribute_equally,
            $current->evaluation_type,
            $this->has('has_cv') ? $this->boolean('has_cv') : $current->has_cv,
            $this->has('assessor_note_label') ? $this->localizedText('assessor_note_label') : $current->assessor_note_label,
            $this->attachments(),
        );
    }
}
