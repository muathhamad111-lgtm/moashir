<?php

declare(strict_types=1);

namespace App\Support\Files;

final class ContentSignature
{
    private const array MAGIC_BYTES = [
        'application/pdf' => '%PDF-',
        'image/png' => "\x89PNG\r\n\x1a\n",
        'image/jpeg' => "\xFF\xD8\xFF",
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => "PK\x03\x04",
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => "PK\x03\x04",
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => "PK\x03\x04",
    ];

    private const string SVG_MIME_TYPE = 'image/svg+xml';

    private const string BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    public static function matches(string $mimeType, string $head): bool
    {
        if ($mimeType === 'text/plain') {
            return $head !== '' && ! str_contains($head, "\0");
        }

        if ($mimeType === self::SVG_MIME_TYPE) {
            return str_starts_with(ltrim(self::withoutByteOrderMark($head)), '<');
        }

        $magic = self::MAGIC_BYTES[$mimeType] ?? null;

        return $magic !== null && str_starts_with($head, $magic);
    }

    private static function withoutByteOrderMark(string $head): string
    {
        return str_starts_with($head, self::BYTE_ORDER_MARK) ? substr($head, strlen(self::BYTE_ORDER_MARK)) : $head;
    }
}
