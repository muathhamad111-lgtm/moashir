<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum DevelopmentSector: string
{
    case Educational = 'educational';
    case Social = 'social';
    case Health = 'health';
    case Developmental = 'developmental';
}
