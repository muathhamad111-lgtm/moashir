<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

final class MetaFieldName
{
    public static function of(string $key): string
    {
        return __('cms.meta_fields.'.$key);
    }

    public static function inLocale(string $name, string $locale): string
    {
        return __('cms.meta_field_in_locale.'.$locale, ['field' => $name]);
    }
}
