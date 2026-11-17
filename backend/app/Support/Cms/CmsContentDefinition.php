<?php

declare(strict_types=1);

namespace App\Support\Cms;

final class CmsContentDefinition
{
    private const array CONTENT_FIELDS = ['title', 'subtitle', 'description', 'cta_label', 'cta_url', 'icon', 'meta'];

    private const string ENABLED_FIELD = 'enabled';

    private const string ITEMS_FIELD = 'items';

    /**
     * @param  array<array-key, mixed>  $definition
     * @param  list<string>  $extraFields
     * @return array<string, mixed>
     */
    public static function attributesOf(array $definition, array $extraFields = []): array
    {
        $attributes = [];

        foreach ([...self::CONTENT_FIELDS, ...$extraFields] as $field) {
            $attributes[$field] = $definition[$field] ?? null;
        }

        return $attributes;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    public static function isEnabled(array $definition): bool
    {
        return ($definition[self::ENABLED_FIELD] ?? true) !== false;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @return list<array<array-key, mixed>>
     */
    public static function itemsOf(array $definition): array
    {
        $items = $definition[self::ITEMS_FIELD] ?? [];

        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }
}
