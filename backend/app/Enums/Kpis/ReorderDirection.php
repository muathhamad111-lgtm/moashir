<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

enum ReorderDirection: string
{
    case Up = 'up';
    case Down = 'down';

    public function offset(): int
    {
        return $this === self::Up ? -1 : 1;
    }
}
