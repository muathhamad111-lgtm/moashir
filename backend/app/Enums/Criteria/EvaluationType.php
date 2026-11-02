<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum EvaluationType: string
{
    case Direct = 'direct';
    case GuidingPoints = 'guiding_points';

    public function scoresGuidingPoints(): bool
    {
        return $this === self::GuidingPoints;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
