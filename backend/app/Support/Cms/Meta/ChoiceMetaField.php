<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

use BackedEnum;
use Illuminate\Validation\Rule;

final readonly class ChoiceMetaField implements MetaField
{
    /**
     * @param  list<BackedEnum>  $choices
     */
    public function __construct(private string $key, private array $choices) {}

    public function key(): string
    {
        return $this->key;
    }

    public function rules(string $attribute): array
    {
        return [$attribute => ['sometimes', 'required', 'string', Rule::in(ChoiceValues::of($this->choices))]];
    }

    public function attributeNames(string $attribute): array
    {
        return [$attribute => MetaFieldName::of($this->key)];
    }
}
