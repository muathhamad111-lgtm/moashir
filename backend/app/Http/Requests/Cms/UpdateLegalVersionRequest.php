<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\LegalDraftChanges;

final class UpdateLegalVersionRequest extends LegalVersionRequest
{
    private const array LOCKED_FIELDS = ['id', 'kind', 'status', 'is_current', 'effective_from', 'published_at'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->textRules(partial: true),
            ...array_fill_keys(self::LOCKED_FIELDS, ['prohibited']),
        ];
    }

    public function changes(): LegalDraftChanges
    {
        return new LegalDraftChanges($this->versionLabel(), $this->localizedText('title'), $this->localizedText('body'));
    }
}
