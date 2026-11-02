<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\GuidingPointAttributes;
use App\Support\ValueObjects\LocalizedText;

final class StoreGuidingPointRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->localizedTextRules('description', 'required', self::DESCRIPTION_MAX),
            ...$this->weightRules('nullable'),
            ...$this->attachmentRules(),
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributesData(): GuidingPointAttributes
    {
        return new GuidingPointAttributes(
            $this->localizedText('description') ?? LocalizedText::fromArray([]),
            $this->weight(),
            $this->attachments(),
        );
    }

    public function isActive(): bool
    {
        return $this->boolean('active', true);
    }
}
