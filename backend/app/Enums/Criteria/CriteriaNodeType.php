<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum CriteriaNodeType: string
{
    case Main = 'main';
    case Sub = 'sub';
    case Point = 'point';
    case Attachment = 'attachment';
    case Version = 'version';

    /**
     * @return list<string>
     */
    public function textFields(): array
    {
        return match ($this) {
            self::Main => ['name', 'description'],
            self::Sub => ['name', 'description', 'assessor_note_label'],
            self::Point => ['description'],
            self::Attachment => ['name'],
            self::Version => ['label'],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
