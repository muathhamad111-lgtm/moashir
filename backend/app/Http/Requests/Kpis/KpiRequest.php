<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Data\Files\ImageChange;
use App\Data\Kpis\KpiChanges;
use App\Enums\Kpis\KpiColorToken;
use App\Enums\Kpis\KpiStatus;
use App\Rules\FontAwesomeIcon;
use App\Rules\KpiNumberInput;
use App\Support\Localization\LocalizedFieldRules;
use App\Support\ScalarValue;
use App\Support\ValueObjects\KpiNumber;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class KpiRequest extends FormRequest
{
    private const array LOCALIZED_FIELDS = [
        'unit' => 60, 'short_description' => 1000, 'full_description' => 10000, 'owner' => 200,
        'data_source' => 300, 'calc_method' => 2000, 'notes' => 2000,
    ];

    private const array NUMBER_FIELDS = ['value', 'target', 'baseline'];

    private const array ENUM_FIELDS = [
        'status' => KpiStatus::class,
        'card_color_token' => KpiColorToken::class,
        'badge_color_token' => KpiColorToken::class,
    ];

    /**
     * @return array<string, mixed>
     */
    protected function kpiRules(bool $partial): array
    {
        $rules = LocalizedFieldRules::required('name', 200, $partial);

        foreach (self::LOCALIZED_FIELDS as $field => $max) {
            $rules = [...$rules, ...LocalizedFieldRules::optional($field, $max)];
        }

        foreach (self::NUMBER_FIELDS as $field) {
            $rules[$field] = ['sometimes', 'nullable', new KpiNumberInput];
        }

        foreach (self::ENUM_FIELDS as $field => $enum) {
            $rules[$field] = ['sometimes', 'nullable', Rule::enum($enum)];
        }

        return [
            ...$rules,
            'slug' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'kpi_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('kpi_categories', 'id')->whereNull('deleted_at')],
            'kpi_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('kpi_types', 'id')],
            'measurement_year' => ['sometimes', 'nullable', 'integer', 'between:2000,2100'],
            'external_link' => ['sometimes', 'nullable', 'string', 'max:300', 'url:https'],
            'icon' => ['sometimes', 'nullable', 'string', new FontAwesomeIcon],
            'image_file_id' => ['sometimes', 'nullable', 'uuid'],
            'show_on_homepage' => ['sometimes', 'boolean'],
            'published' => ['prohibited'],
            'order' => ['prohibited'],
            'id' => ['prohibited'],
        ];
    }

    public function changes(): KpiChanges
    {
        $attributes = [];

        foreach (['name', ...array_keys(self::LOCALIZED_FIELDS)] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $attributes[$field] = is_array($value) ? LocalizedContent::tryFromArray($value) : null;
            }
        }

        foreach (self::NUMBER_FIELDS as $field) {
            if ($this->has($field)) {
                $attributes[$field] = KpiNumber::tryFrom($this->input($field));
            }
        }

        foreach (self::ENUM_FIELDS as $field => $enum) {
            if ($this->has($field)) {
                $attributes[$field] = $this->enum($field, $enum);
            }
        }

        return new KpiChanges([...$attributes, ...$this->scalarAttributes()], $this->imageChange());
    }

    /**
     * @return array<string, mixed>
     */
    private function scalarAttributes(): array
    {
        $attributes = [];

        foreach (['kpi_category_id', 'kpi_type_id', 'measurement_year'] as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->filled($field) ? ScalarValue::int($this->input($field)) : null;
            }
        }

        foreach (['slug', 'external_link', 'icon'] as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->filled($field) ? $this->string($field)->trim()->toString() : null;
            }
        }

        if ($this->has('show_on_homepage')) {
            $attributes['show_on_homepage'] = $this->boolean('show_on_homepage');
        }

        return $attributes;
    }

    private function imageChange(): ?ImageChange
    {
        if (! $this->has('image_file_id')) {
            return null;
        }

        return new ImageChange($this->filled('image_file_id') ? $this->string('image_file_id')->toString() : null);
    }
}
