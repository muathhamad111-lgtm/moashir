<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

final readonly class IntegerMetaField implements MetaField
{
    public function __construct(private string $key, private int $min, private int $max) {}

    public function key(): string
    {
        return $this->key;
    }

    public function rules(string $attribute): array
    {
        return [$attribute => ['sometimes', 'required', 'integer:strict', 'between:'.$this->min.','.$this->max]];
    }

    public function attributeNames(string $attribute): array
    {
        return [$attribute => MetaFieldName::of($this->key)];
    }
}
