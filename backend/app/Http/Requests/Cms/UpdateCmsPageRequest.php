<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\CmsPageChanges;
use App\Support\Localization\LocalizedFieldRules;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCmsPageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...LocalizedFieldRules::optional('title', 200),
            'seo' => ['sometimes', 'nullable', 'array:title,description'],
            ...LocalizedFieldRules::optional('seo.title', 120),
            ...LocalizedFieldRules::optional('seo.description', 300),
            'key' => ['prohibited'],
            'published' => ['prohibited'],
            'sections' => ['prohibited'],
        ];
    }

    public function changes(): CmsPageChanges
    {
        $attributes = [];

        if ($this->has('title')) {
            $attributes['title'] = $this->localized($this->input('title'));
        }

        if ($this->has('seo')) {
            $attributes['seo'] = [
                'title' => $this->localized($this->input('seo.title'))?->toArray(),
                'description' => $this->localized($this->input('seo.description'))?->toArray(),
            ];
        }

        return new CmsPageChanges($attributes);
    }

    private function localized(mixed $value): ?LocalizedContent
    {
        return is_array($value) ? LocalizedContent::tryFromArray($value) : null;
    }
}
