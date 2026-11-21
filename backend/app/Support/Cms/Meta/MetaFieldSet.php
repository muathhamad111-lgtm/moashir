<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

final readonly class MetaFieldSet
{
    private const string PREFIX = 'meta.';

    /**
     * @param  list<MetaField>  $fields
     */
    public function __construct(private array $fields) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $rules = [...$rules, ...$field->rules(self::PREFIX.$field->key())];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributeNames(): array
    {
        $names = [];

        foreach ($this->fields as $field) {
            $names = [...$names, ...$field->attributeNames(self::PREFIX.$field->key())];
        }

        return $names;
    }

    /**
     * @param  array<array-key, mixed>  $meta
     * @return array<array-key, mixed>
     */
    public function cleaned(array $meta): array
    {
        foreach ($this->fields as $field) {
            if ($field instanceof LocalizedMetaField && array_key_exists($field->key(), $meta)) {
                $meta[$field->key()] = $field->clean($meta[$field->key()]);
            }
        }

        return $meta;
    }
}
