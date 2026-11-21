<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

enum EntityLevelBucket: string
{
    case None = 'none';
    case InProgress = 'in_progress';
    case Participation = 'participation';
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Diamond = 'diamond';

    public static function of(?EntityLevel $level, bool $hasOpenAccreditedRequest): self
    {
        if ($level instanceof EntityLevel) {
            return self::from($level->value);
        }

        return $hasOpenAccreditedRequest ? self::InProgress : self::None;
    }
}
