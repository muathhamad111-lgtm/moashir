<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\NewCmsSection;
use App\Enums\Cms\CmsSectionType;
use App\Support\Cms\Meta\MetaFieldSet;
use App\Support\Cms\MetaSchemas\SectionMetaSchemas;
use Illuminate\Validation\Rule;

final class StoreCmsSectionRequest extends CmsContentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->contentRules(),
            ...$this->lockedFieldRules(['key', 'type', 'enabled']),
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'type' => ['required', Rule::enum(CmsSectionType::class)],
            'enabled' => ['sometimes', 'boolean'],
            'background' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[a-z][a-z0-9-]*$/'],
        ];
    }

    protected function metaFields(): MetaFieldSet
    {
        $type = $this->input('type');

        return new MetaFieldSet(SectionMetaSchemas::of(is_string($type) ? CmsSectionType::tryFrom($type) : null)->sectionFields());
    }

    public function section(): NewCmsSection
    {
        return new NewCmsSection(
            $this->string('key')->toString(),
            $this->enum('type', CmsSectionType::class) ?? CmsSectionType::RichText,
            $this->boolean('enabled', true),
            $this->contentChanges(['background']),
        );
    }
}
