<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Rules\FreeOfHtmlMarkup;
use App\Support\RichText\RichTextSanitizer;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

abstract class LegalVersionRequest extends FormRequest
{
    private const string VERSION_PATTERN = '/^[A-Za-z0-9._-]{1,20}$/';

    private const array TEXT_LIMITS = ['title' => 200, 'body' => 60000];

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['version' => __('legal.attributes.version')];

        foreach (array_keys(self::TEXT_LIMITS) as $field) {
            $attributes[$field] = __('legal.attributes.'.$field);
            $attributes[$field.'.ar'] = __('legal.attributes.'.$field.'_ar');
            $attributes[$field.'.en'] = __('legal.attributes.'.$field.'_en');
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        foreach (array_keys(self::TEXT_LIMITS) as $field) {
            $value = $this->input($field);

            if (is_array($value)) {
                $sanitized[$field] = $this->sanitized($field, $value);
            }
        }

        if (is_string($this->input('version'))) {
            $sanitized['version'] = trim($this->string('version')->toString());
        }

        $this->merge($sanitized);
    }

    /**
     * @return array<string, mixed>
     */
    protected function textRules(bool $partial): array
    {
        $presence = $partial ? ['sometimes', 'required'] : ['required'];
        $rules = ['version' => [...$presence, 'string', 'regex:'.self::VERSION_PATTERN]];

        foreach (self::TEXT_LIMITS as $field => $max) {
            $rules[$field] = [...$presence, 'array:ar,en'];
            $rules[$field.'.ar'] = ['required_with:'.$field, 'string', 'max:'.$max, new FreeOfHtmlMarkup];
            $rules[$field.'.en'] = ['required_with:'.$field, 'string', 'max:'.$max, new FreeOfHtmlMarkup];
        }

        return $rules;
    }

    protected function localizedText(string $field): ?LocalizedText
    {
        $value = $this->validated($field);

        return is_array($value) ? LocalizedText::fromArray($value) : null;
    }

    protected function versionLabel(): ?string
    {
        $value = $this->validated('version');

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sanitized(string $field, array $value): array
    {
        foreach (['ar', 'en'] as $locale) {
            if (is_string($value[$locale] ?? null)) {
                $value[$locale] = $field === 'title' ? RichTextSanitizer::singleLine($value[$locale]) : RichTextSanitizer::clean($value[$locale]);
            }
        }

        return $value;
    }
}
