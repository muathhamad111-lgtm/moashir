<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum EntityRequestPath: string
{
    case SelfAssessment = 'self';
    case Accredited = 'accredited';
    case Advanced = 'advanced';
    case Bag = 'bag';
}
