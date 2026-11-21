<?php

declare(strict_types=1);

namespace App\Enums\Files;

enum WatermarkFormat: string
{
    case Pdf = 'pdf';
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Office = 'office';

    private const array OFFICE_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public static function ofMimeType(string $mimeType): ?self
    {
        return match (true) {
            $mimeType === 'application/pdf' => self::Pdf,
            $mimeType === 'image/jpeg' => self::Jpeg,
            $mimeType === 'image/png' => self::Png,
            in_array($mimeType, self::OFFICE_MIME_TYPES, true) => self::Office,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function officeMimeTypes(): array
    {
        return self::OFFICE_MIME_TYPES;
    }

    public function isImage(): bool
    {
        return $this === self::Jpeg || $this === self::Png;
    }

    public function deliveredMimeType(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Pdf, self::Office => 'application/pdf',
        };
    }
}
