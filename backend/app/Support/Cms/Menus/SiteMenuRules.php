<?php

declare(strict_types=1);

namespace App\Support\Cms\Menus;

use App\Rules\SafeLinkUrl;
use App\Support\Localization\LocalizedFieldRules;

final class SiteMenuRules
{
    public const int HEADER_MAX_ITEMS = 8;

    public const int HEADER_MAX_CHILDREN = 8;

    public const int HEADER_LABEL_MAX = 40;

    public const int FOOTER_MAX_ITEMS = 12;

    public const int FOOTER_LABEL_MAX = 80;

    private const int URL_MAX = 300;

    /**
     * @return array<string, mixed>
     */
    public static function header(string $field): array
    {
        $children = $field.'.*.children';

        return [
            $field => ['sometimes', 'array', 'max:'.self::HEADER_MAX_ITEMS],
            $field.'.*' => ['required', 'array:label,url,is_visible,children'],
            ...LocalizedFieldRules::required($field.'.*.label', self::HEADER_LABEL_MAX),
            $field.'.*.url' => ['nullable', 'string', 'max:'.self::URL_MAX, new SafeLinkUrl, 'required_without:'.$children],
            $field.'.*.is_visible' => ['sometimes', 'boolean'],
            $children => ['sometimes', 'array', 'max:'.self::HEADER_MAX_CHILDREN],
            ...self::flatItem($children.'.*', self::HEADER_LABEL_MAX),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function footer(string $field): array
    {
        return [
            $field => ['sometimes', 'array', 'max:'.self::FOOTER_MAX_ITEMS],
            ...self::flatItem($field.'.*', self::FOOTER_LABEL_MAX),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function flatItem(string $item, int $labelMax): array
    {
        return [
            $item => ['required', 'array:label,url,is_visible'],
            ...LocalizedFieldRules::required($item.'.label', $labelMax),
            $item.'.url' => ['required', 'string', 'max:'.self::URL_MAX, new SafeLinkUrl],
            $item.'.is_visible' => ['sometimes', 'boolean'],
        ];
    }
}
