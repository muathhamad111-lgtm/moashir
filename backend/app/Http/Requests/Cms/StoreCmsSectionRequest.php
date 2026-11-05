<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\NewCmsSection;
use App\Enums\Cms\CmsSectionType;
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
