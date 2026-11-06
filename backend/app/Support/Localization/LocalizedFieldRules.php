<?php

declare(strict_types=1);

namespace App\Support\Localization;

final class LocalizedFieldRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function optional(string $field, int $max): array
    {
        return [
            $field => ['sometimes', 'nullable', 'array:ar,en'],
            $field.'.ar' => ['nullable', 'string', 'max:'.$max],
            $field.'.en' => ['nullable', 'string', 'max:'.$max],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function required(string $field, int $max, bool $partial = false): array
    {
        return [
            $field => $partial ? ['sometimes', 'required', 'array:ar,en'] : ['required', 'array:ar,en'],
            $field.'.ar' => ['sometimes', 'nullable', 'string', 'max:'.$max, 'required_without:'.$field.'.en'],
            $field.'.en' => ['sometimes', 'nullable', 'string', 'max:'.$max, 'required_without:'.$field.'.ar'],
        ];
    }
}
