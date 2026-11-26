<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\NewCmsItem;
use App\Support\Cms\Items\PartnerItemRules;
use App\Support\Cms\Meta\MetaFieldSet;
use App\Support\Cms\MetaSchemas\SectionMetaSchemas;

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
            ...($this->routeSectionType()?->hasLogoItems() ? PartnerItemRules::forCreate() : []),
        ];
    }

    protected function metaFields(): MetaFieldSet
    {
        return new MetaFieldSet(SectionMetaSchemas::of($this->routeSectionType())->itemFields());
    }

    public function item(): NewCmsItem
    {
        return new NewCmsItem($this->boolean('enabled', true), $this->contentChanges());
    }
}
