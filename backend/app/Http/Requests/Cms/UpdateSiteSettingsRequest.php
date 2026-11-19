<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Data\Cms\SiteSettingsChanges;
use App\Enums\Cms\SocialPlatform;
use App\Support\Cms\Menus\SiteMenuItems;
use App\Support\Cms\Menus\SiteMenuRules;
use App\Support\Localization\LocalizedFieldRules;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSiteSettingsRequest extends FormRequest
{
    private const array FIELDS = ['platform_name', 'platform_description', 'social', 'contact', 'footer_note', 'quick_links', 'header_links', 'seo', 'featured_kpi_limit', 'show_featured_kpis'];

    private const array LOCALIZED_FIELDS = ['platform_name', 'platform_description', 'footer_note'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...LocalizedFieldRules::complete('platform_name', 120),
            ...LocalizedFieldRules::complete('platform_description', 300),
            'social' => ['sometimes', 'array', 'max:10'],
            'social.*' => ['required', 'array:platform,url'],
            'social.*.platform' => ['required', 'distinct', Rule::enum(SocialPlatform::class)],
            'social.*.url' => ['required', 'string', 'max:300', 'url:https'],
            'contact' => ['sometimes', 'nullable', 'array:email,phone,address'],
            'contact.email' => ['nullable', 'string', 'max:150', 'email:strict'],
            'contact.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{6,30}$/'],
            ...LocalizedFieldRules::optional('contact.address', 300),
            ...LocalizedFieldRules::optional('footer_note', 500),
            ...SiteMenuRules::footer('quick_links'),
            ...SiteMenuRules::header('header_links'),
            'seo' => ['sometimes', 'nullable', 'array:title,description'],
            ...LocalizedFieldRules::optional('seo.title', 120),
            ...LocalizedFieldRules::optional('seo.description', 300),
            'featured_kpi_limit' => ['sometimes', 'integer', 'between:'.config()->integer('moashir.kpis.featured_min').','.config()->integer('moashir.kpis.featured_max')],
            'show_featured_kpis' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_diff(array_keys($this->all()), self::FIELDS) as $unknown) {
                    $validator->errors()->add((string) $unknown, __('validation.custom.key.unknown'));
                }
            },
        ];
    }

    public function changes(): SiteSettingsChanges
    {
        $attributes = [];

        if ($this->has('social')) {
            $attributes['social'] = array_values(array_map(static fn (array $link): array => [
                'platform' => ScalarValue::string($link['platform'] ?? null),
                'url' => trim(ScalarValue::string($link['url'] ?? null)),
            ], $this->listOf('social')));
        }

        if ($this->has('contact')) {
            $attributes['contact'] = $this->contact();
        }

        foreach (self::LOCALIZED_FIELDS as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->localized($this->input($field));
            }
        }

        if ($this->has('quick_links')) {
            $attributes['quick_links'] = SiteMenuItems::footer($this->listOf('quick_links'));
        }

        if ($this->has('header_links')) {
            $attributes['header_links'] = SiteMenuItems::header($this->listOf('header_links'));
        }

        if ($this->has('seo')) {
            $attributes['seo'] = $this->filled('seo') ? [
                'title' => $this->localized($this->input('seo.title'))?->toArray(),
                'description' => $this->localized($this->input('seo.description'))?->toArray(),
            ] : null;
        }

        foreach (['featured_kpi_limit' => 'integer', 'show_featured_kpis' => 'boolean'] as $field => $type) {
            if ($this->has($field)) {
                $attributes[$field] = $type === 'integer' ? $this->integer($field) : $this->boolean($field);
            }
        }

        return new SiteSettingsChanges($attributes);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contact(): ?array
    {
        if (! $this->filled('contact')) {
            return null;
        }

        return [
            'email' => $this->filled('contact.email') ? $this->string('contact.email')->trim()->toString() : null,
            'phone' => $this->filled('contact.phone') ? $this->string('contact.phone')->trim()->toString() : null,
            'address' => $this->localized($this->input('contact.address'))?->toArray(),
        ];
    }

    private function localized(mixed $value): ?LocalizedContent
    {
        return is_array($value) ? LocalizedContent::tryFromArray($value) : null;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function listOf(string $field): array
    {
        $items = $this->input($field);

        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }
}
