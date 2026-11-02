<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum EntityType: string
{
    case CivilAssociation = 'civil_association';
    case CivilFoundation = 'civil_foundation';
    case Waqf = 'waqf';
}
