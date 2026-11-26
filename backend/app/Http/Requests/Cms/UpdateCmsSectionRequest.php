<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\CmsContentChanges;
use App\Support\Cms\Meta\MetaFieldSet;
use App\Support\Cms\MetaSchemas\SectionMetaSchemas;

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

    protected function metaFields(): MetaFieldSet
    {
        return new MetaFieldSet(SectionMetaSchemas::of($this->routeSectionType())->sectionFields());
    }

    public function changes(): CmsContentChanges
    {
        return $this->contentChanges(['background']);
    }
}
