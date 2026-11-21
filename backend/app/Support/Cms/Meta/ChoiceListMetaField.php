<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

use BackedEnum;
use Illuminate\Validation\Rule;

final readonly class ChoiceListMetaField implements MetaField
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
        return [
            $attribute => ['sometimes', 'required', 'list', 'min:1', 'max:'.count($this->choices)],
            $attribute.'.*' => ['required', 'string', 'distinct', Rule::in(ChoiceValues::of($this->choices))],
        ];
    }

    public function attributeNames(string $attribute): array
    {
        $name = MetaFieldName::of($this->key);

        return [$attribute => $name, $attribute.'.*' => $name];
    }
}
