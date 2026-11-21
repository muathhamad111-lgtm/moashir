<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\CmsContentChanges;
use App\Data\Files\ImageChange;
use App\Enums\Cms\CmsAvailability;
use App\Enums\Cms\CmsSectionType;
use App\Models\Cms\CmsSection;
use App\Rules\BoundedContentMeta;
use App\Rules\FontAwesomeIcon;
use App\Rules\FreeOfHtmlMarkup;
use App\Rules\SafeLinkUrl;
use App\Support\Cms\Meta\MetaFieldSet;
use App\Support\Localization\LocalizedFieldRules;
use App\Support\RichText\RichTextSanitizer;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CmsContentRequest extends FormRequest
{
    private const array LOCALIZED_FIELDS = ['title' => 300, 'subtitle' => 500, 'description' => 5000, 'cta_label' => 120];

    private const array LOCKED_FIELDS = ['id', 'key', 'type', 'order', 'sort_order', 'items', 'enabled', 'is_enabled'];

    private const string RICH_TEXT_FIELD = 'description';

    abstract protected function metaFields(): MetaFieldSet;

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->metaFields()->attributeNames();
    }

    protected function prepareForValidation(): void
    {
        $this->cleanMeta();
        $description = $this->input(self::RICH_TEXT_FIELD);

        if (! is_array($description)) {
            return;
        }

        foreach (['ar', 'en'] as $locale) {
            if (is_string($description[$locale] ?? null)) {
                $description[$locale] = RichTextSanitizer::clean($description[$locale]);
            }
        }

        $this->merge([self::RICH_TEXT_FIELD => $description]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function contentRules(): array
    {
        $rules = [];

        foreach (self::LOCALIZED_FIELDS as $field => $max) {
            $rules = [...$rules, ...LocalizedFieldRules::optional($field, $max)];
        }

        $rules[self::RICH_TEXT_FIELD.'.ar'][] = new FreeOfHtmlMarkup;
        $rules[self::RICH_TEXT_FIELD.'.en'][] = new FreeOfHtmlMarkup;

        return [
            ...$rules,
            'cta_url' => ['sometimes', 'nullable', 'string', 'max:300', new SafeLinkUrl],
            'icon' => ['sometimes', 'nullable', 'string', new FontAwesomeIcon],
            'image_file_id' => ['sometimes', 'nullable', 'uuid'],
            'meta' => ['sometimes', 'nullable', new BoundedContentMeta(config()->integer('moashir.cms.meta_max_bytes'))],
            'meta.availability' => ['sometimes', Rule::enum(CmsAvailability::class)],
            ...$this->metaFields()->rules(),
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, list<string>>
     */
    protected function lockedFieldRules(array $allowed = []): array
    {
        $rules = [];

        foreach (array_diff(self::LOCKED_FIELDS, $allowed) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @param  list<string>  $extraColumns
     */
    protected function contentChanges(array $extraColumns = []): CmsContentChanges
    {
        $attributes = [];

        foreach (array_keys(self::LOCALIZED_FIELDS) as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $attributes[$field] = is_array($value) ? LocalizedContent::tryFromArray($value) : null;
            }
        }

        foreach (['cta_url', 'icon', ...$extraColumns] as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->filled($field) ? $this->string($field)->trim()->toString() : null;
            }
        }

        if ($this->has('meta')) {
            $meta = $this->input('meta');
            $attributes['meta'] = is_array($meta) && $meta !== [] ? $meta : null;
        }

        return new CmsContentChanges($attributes, $this->imageChange());
    }

    protected function routeSectionType(): ?CmsSectionType
    {
        $section = $this->route('section');

        return $section instanceof CmsSection ? $section->type : null;
    }

    private function cleanMeta(): void
    {
        $meta = $this->input('meta');

        if (is_array($meta)) {
            $this->merge(['meta' => $this->metaFields()->cleaned($meta)]);
        }
    }

    private function imageChange(): ?ImageChange
    {
        if (! $this->has('image_file_id')) {
            return null;
        }

        return new ImageChange($this->filled('image_file_id') ? $this->string('image_file_id')->toString() : null);
    }
}
