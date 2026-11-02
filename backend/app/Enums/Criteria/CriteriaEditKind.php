<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum CriteriaEditKind: string
{
    case TextCorrection = 'text_correction';
    case Structural = 'structural';

    public function reachesClosedAssessments(): bool
    {
        return $this === self::TextCorrection;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
