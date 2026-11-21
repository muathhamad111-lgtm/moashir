<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\CmsContentChanges;
use App\Support\Cms\Meta\MetaFieldSet;
use App\Support\Cms\MetaSchemas\SectionMetaSchemas;

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

    protected function metaFields(): MetaFieldSet
    {
        return new MetaFieldSet(SectionMetaSchemas::of($this->routeSectionType())->itemFields());
    }

    public function changes(): CmsContentChanges
    {
        return $this->contentChanges();
    }
}
