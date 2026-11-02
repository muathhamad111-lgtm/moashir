<?php

declare(strict_types=1);

namespace App\Enums\Files;

enum EvidenceFileType: string
{
    case Pdf = 'pdf';
    case Docx = 'docx';
    case Xlsx = 'xlsx';
    case Pptx = 'pptx';
    case Jpg = 'jpg';
    case Png = 'png';

    /**
     * @return array<string, list<string>>
     */
    public function uploadTypes(): array
    {
        return match ($this) {
            self::Pdf => ['pdf' => ['application/pdf']],
            self::Docx => ['docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
            self::Xlsx => ['xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']],
            self::Pptx => ['pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation']],
            self::Jpg => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']],
            self::Png => ['png' => ['image/png']],
        };
    }

    /**
     * @param  list<self>  $types
     * @return array<string, list<string>>
     */
    public static function uploadTypesOf(array $types): array
    {
        $allowed = [];

        foreach ($types as $type) {
            $allowed = [...$allowed, ...$type->uploadTypes()];
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
