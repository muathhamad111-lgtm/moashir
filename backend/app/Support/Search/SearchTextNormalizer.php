<?php

declare(strict_types=1);

namespace App\Support\Search;

final class SearchTextNormalizer
{
    public const string DATABASE_FUNCTION = 'moashir_search_normalize';

    private const string IGNORED_MARKS = '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u';

    private const string WHITESPACE = '/\s+/u';

    private const array EQUIVALENT_CHARACTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function normalize(string $text): string
    {
        $withoutMarks = (string) preg_replace(self::IGNORED_MARKS, '', $text);
        $unified = strtr(mb_strtolower($withoutMarks), self::EQUIVALENT_CHARACTERS);

        return trim((string) preg_replace(self::WHITESPACE, ' ', $unified));
    }
}
