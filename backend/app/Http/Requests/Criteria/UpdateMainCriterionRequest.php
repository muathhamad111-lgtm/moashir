<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\MainCriterionAttributes;
use App\Enums\Criteria\CriterionGroup;
use App\Models\Criteria\MainCriterion;
use Illuminate\Validation\Rule;

final class UpdateMainCriterionRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->localizedTextRules('name', 'sometimes', self::NAME_MAX),
            ...$this->localizedTextRules('description', 'nullable', self::DESCRIPTION_MAX),
            ...$this->weightRules('sometimes'),
            'distribute_equally' => ['sometimes', 'boolean'],
            'group' => ['sometimes', Rule::enum(CriterionGroup::class)],
        ];
    }

    public function attributesData(MainCriterion $current): MainCriterionAttributes
    {
        return new MainCriterionAttributes(
            $this->localizedText('name') ?? $current->name,
            $this->has('description') ? $this->localizedText('description') : $current->description,
            $this->weight() ?? $current->weight,
            $this->has('distribute_equally') ? $this->boolean('distribute_equally') : $current->distribute_equally,
            $this->enum('group', CriterionGroup::class) ?? $current->group,
        );
    }
}
