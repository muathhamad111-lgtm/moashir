<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\NewCmsItem;

final class StoreCmsItemRequest extends CmsContentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->contentRules(),
            ...$this->lockedFieldRules(['enabled']),
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function item(): NewCmsItem
    {
        return new NewCmsItem($this->boolean('enabled', true), $this->contentChanges());
    }
}
