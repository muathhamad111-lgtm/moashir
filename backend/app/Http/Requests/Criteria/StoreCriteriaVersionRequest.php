<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Support\ValueObjects\LocalizedText;

final class StoreCriteriaVersionRequest extends CriteriaFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'clone_from_version_id' => ['nullable', 'integer', 'min:1'],
            ...$this->localizedTextRules('label', 'nullable', self::NAME_MAX),
        ];
    }

    public function cloneFromVersionId(): ?int
    {
        return $this->filled('clone_from_version_id') ? $this->integer('clone_from_version_id') : null;
    }

    public function label(): ?LocalizedText
    {
        return $this->localizedText('label');
    }
}
