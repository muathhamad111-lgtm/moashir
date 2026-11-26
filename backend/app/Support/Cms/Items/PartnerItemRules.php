<?php

declare(strict_types=1);

namespace App\Support\Cms\Items;

use App\Rules\HttpsLinkUrl;
use App\Support\Localization\LocalizedFieldRules;

/**
 * A partner is a name in both languages, a logo and an optional https link that opens in a new tab: the generic
 * content fields that the landing never shows for a partner are refused.
 */
final class PartnerItemRules
{
    private const int NAME_MAX = 300;

    private const int LINK_MAX = 300;

    private const array UNUSED_FIELDS = ['subtitle', 'description', 'cta_label', 'icon', 'meta'];

    /**
     * @return array<string, mixed>
     */
    public static function forCreate(): array
    {
        return [
            ...self::shared(),
            'title' => ['required', 'array:ar,en'],
            'title.ar' => ['required', 'string', 'max:'.self::NAME_MAX],
            'title.en' => ['required', 'string', 'max:'.self::NAME_MAX],
            'image_file_id' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forUpdate(): array
    {
        return [
            ...self::shared(),
            ...LocalizedFieldRules::complete('title', self::NAME_MAX),
            'image_file_id' => ['sometimes', 'required', 'uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shared(): array
    {
        $rules = ['cta_url' => ['sometimes', 'nullable', 'string', 'max:'.self::LINK_MAX, new HttpsLinkUrl]];

        foreach (self::UNUSED_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
