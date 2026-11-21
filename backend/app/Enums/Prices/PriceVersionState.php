<?php

declare(strict_types=1);

namespace App\Enums\Prices;

enum PriceVersionState: string
{
    case InForce = 'in_force';
    case Scheduled = 'scheduled';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
