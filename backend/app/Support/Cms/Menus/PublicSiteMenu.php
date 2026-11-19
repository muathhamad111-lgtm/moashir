<?php

declare(strict_types=1);

namespace App\Support\Cms\Menus;

use App\Support\ValueObjects\LocalizedContent;

final class PublicSiteMenu
{
    /**
     * @param  array<array-key, mixed>  $stored
     * @return list<array{label: array{ar: string, en: string}|null, url: string|null, children: list<array{label: array{ar: string, en: string}|null, url: string}>}>
     */
    public static function header(array $stored): array
    {
        $items = [];

        foreach (SiteMenuItems::header($stored) as $item) {
            $children = self::visible($item['children']);

            if (! $item['is_visible'] || ($item['url'] === null && $children === [])) {
                continue;
            }

            $items[] = ['label' => self::label($item['label']), 'url' => $item['url'], 'children' => $children];
        }

        return $items;
    }

    /**
     * @param  array<array-key, mixed>  $stored
     * @return list<array{label: array{ar: string, en: string}|null, url: string}>
     */
    public static function footer(array $stored): array
    {
        return self::visible(SiteMenuItems::footer($stored));
    }

    /**
     * @param  list<array{label: array<string, string>|null, url: string, is_visible: bool}>  $items
     * @return list<array{label: array{ar: string, en: string}|null, url: string}>
     */
    private static function visible(array $items): array
    {
        $visible = array_filter($items, static fn (array $item): bool => $item['is_visible']);

        return array_values(array_map(static fn (array $item): array => ['label' => self::label($item['label']), 'url' => $item['url']], $visible));
    }

    /**
     * @param  array<string, string>|null  $label
     * @return array{ar: string, en: string}|null
     */
    private static function label(?array $label): ?array
    {
        return $label === null ? null : LocalizedContent::tryFromArray($label)?->withFallback();
    }
}
