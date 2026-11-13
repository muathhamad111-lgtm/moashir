<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\CmsContentChanges;

final class UpdateCmsSectionRequest extends CmsContentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->contentRules(),
            ...$this->lockedFieldRules(),
            'background' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[a-z][a-z0-9-]*$/'],
        ];
    }

    public function changes(): CmsContentChanges
    {
        return $this->contentChanges(['background']);
    }
}
