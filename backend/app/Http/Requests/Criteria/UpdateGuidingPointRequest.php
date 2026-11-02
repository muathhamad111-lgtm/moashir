<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\GuidingPointAttributes;
use App\Models\Criteria\GuidingPoint;

final class UpdateGuidingPointRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->localizedTextRules('description', 'sometimes', self::DESCRIPTION_MAX),
            ...$this->weightRules('sometimes'),
            ...$this->attachmentRules(),
            'active' => ['prohibited'],
        ];
    }

    public function attributesData(GuidingPoint $current): GuidingPointAttributes
    {
        return new GuidingPointAttributes(
            $this->localizedText('description') ?? $current->description,
            $this->weight() ?? $current->weight,
            $this->attachments(),
        );
    }
}
