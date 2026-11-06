<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Data\Kpis\KpiCatalogEntry;
use App\Rules\FontAwesomeIcon;
use App\Support\Localization\LocalizedFieldRules;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

abstract class KpiCatalogRequest extends FormRequest
{
    private const string KEY_PATTERN = '/^[a-z0-9]+([_-][a-z0-9]+)*$/';

    abstract protected function table(): string;

    abstract protected function keyLength(): int;

    abstract protected function current(): ?Model;

    abstract protected function usesSoftDeletes(): bool;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $partial = $this->current() instanceof Model;

        return [
            'key' => [$partial ? 'sometimes' : 'required', 'string', 'max:'.$this->keyLength(), 'regex:'.self::KEY_PATTERN, $this->uniqueKey()],
            ...LocalizedFieldRules::required('name', 120, $partial),
            ...LocalizedFieldRules::optional('description', 500),
            'icon' => ['sometimes', 'nullable', 'string', new FontAwesomeIcon],
            'order' => ['sometimes', 'integer', 'between:0,32000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function entry(): KpiCatalogEntry
    {
        $attributes = [];

        foreach (['key' => 'key', 'icon' => 'icon'] as $field => $column) {
            if ($this->has($field)) {
                $attributes[$column] = $this->filled($field) ? $this->string($field)->trim()->toString() : null;
            }
        }

        foreach (['name', 'description'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $attributes[$field] = is_array($value) ? LocalizedContent::tryFromArray($value) : null;
            }
        }

        if ($this->has('order')) {
            $attributes['sort_order'] = $this->integer('order');
        }

        if ($this->has('active')) {
            $attributes['is_active'] = $this->boolean('active');
        }

        return new KpiCatalogEntry($attributes);
    }

    private function uniqueKey(): Unique
    {
        $rule = Rule::unique($this->table(), 'key');

        if ($this->usesSoftDeletes()) {
            $rule->whereNull('deleted_at');
        }

        $current = $this->current();

        return $current instanceof Model ? $rule->ignore($current->getKey()) : $rule;
    }
}
