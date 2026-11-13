<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

enum EntityLevelFilter: string
{
    case None = 'none';
    case Expired = 'expired';
    case Participation = 'participation';
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Diamond = 'diamond';

    public function level(): ?EntityLevel
    {
        return EntityLevel::tryFrom($this->value);
    }
}
