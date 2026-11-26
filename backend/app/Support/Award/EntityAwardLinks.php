<?php

declare(strict_types=1);

namespace App\Support\Award;

final class EntityAwardLinks
{
    public static function cycle(int $cycleId): string
    {
        return '/paths/award?cycle='.$cycleId;
    }
}
