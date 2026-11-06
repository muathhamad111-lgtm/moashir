<?php

declare(strict_types=1);

namespace App\Support\RichText;

final class RichTextSanitizer
{
    private const string INVISIBLE_CONTROLS = '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

    public static function clean(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        $visible = preg_replace(self::INVISIBLE_CONTROLS, '', $normalized) ?? '';
        $trimmedLines = preg_replace('/ +$/m', '', $visible) ?? '';

        return trim(preg_replace("/\n{3,}/", "\n\n", $trimmedLines) ?? '');
    }

    public static function singleLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', self::clean($text)) ?? '');
    }
}
