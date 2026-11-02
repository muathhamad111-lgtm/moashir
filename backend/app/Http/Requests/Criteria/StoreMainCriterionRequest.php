<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\MainCriterionAttributes;
use App\Enums\Criteria\CriterionGroup;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;
use Illuminate\Validation\Rule;

final class StoreMainCriterionRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->localizedTextRules('name', 'required', self::NAME_MAX),
            ...$this->localizedTextRules('description', 'nullable', self::DESCRIPTION_MAX),
            ...$this->weightRules('required'),
            'distribute_equally' => ['sometimes', 'boolean'],
            'group' => ['sometimes', Rule::enum(CriterionGroup::class)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributesData(): MainCriterionAttributes
    {
        return new MainCriterionAttributes(
            $this->localizedText('name') ?? LocalizedText::fromArray([]),
            $this->localizedText('description'),
            $this->weight() ?? Weight::zero(),
            $this->boolean('distribute_equally'),
            $this->enum('group', CriterionGroup::class) ?? CriterionGroup::Enablers,
        );
    }

    public function isActive(): bool
    {
        return $this->boolean('active', true);
    }
}
