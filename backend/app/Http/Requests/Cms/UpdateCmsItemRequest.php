<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\CmsContentChanges;

final class UpdateCmsItemRequest extends CmsContentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->contentRules(),
            ...$this->lockedFieldRules(),
        ];
    }

    public function changes(): CmsContentChanges
    {
        return $this->contentChanges();
    }
}
