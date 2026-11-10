<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum PathAvailability: string
{
    case Available = 'available';
    case Paused = 'paused';
    case Hidden = 'hidden';
}
