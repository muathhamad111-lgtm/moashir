<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum CriteriaVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function acceptsStructuralEdits(): bool
    {
        return $this === self::Draft;
    }

    public function acceptsTextCorrections(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
