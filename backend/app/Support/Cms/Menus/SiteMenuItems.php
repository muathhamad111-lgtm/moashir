<?php

declare(strict_types=1);

namespace App\Support\Cms\Menus;

use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedContent;

final class SiteMenuItems
{
    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array{label: array<string, string>|null, url: string|null, is_visible: bool, children: list<array{label: array<string, string>|null, url: string, is_visible: bool}>}>
     */
    public static function header(array $items): array
    {
        return array_map(static function (array $item): array {
            $children = self::footer(self::listOf($item['children'] ?? null));

            return [...self::flat($item), 'url' => $children === [] ? self::url($item) : null, 'children' => $children];
        }, self::listOf($items));
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array{label: array<string, string>|null, url: string, is_visible: bool}>
     */
    public static function footer(array $items): array
    {
        return array_map(static fn (array $item): array => [...self::flat($item), 'url' => (string) self::url($item)], self::listOf($items));
    }

    /**
     * @param  array<array-key, mixed>  $item
     * @return array{label: array<string, string>|null, is_visible: bool}
     */
    private static function flat(array $item): array
    {
        $label = $item['label'] ?? null;

        return [
            'label' => is_array($label) ? LocalizedContent::tryFromArray($label)?->toArray() : null,
            'is_visible' => ! array_key_exists('is_visible', $item) || filter_var($item['is_visible'], FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private static function url(array $item): ?string
    {
        $url = trim(ScalarValue::string($item['url'] ?? null));

        return $url === '' ? null : $url;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private static function listOf(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }
}
