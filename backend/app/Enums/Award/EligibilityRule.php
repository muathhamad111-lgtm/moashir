<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum EligibilityRule: string
{
    case MinEntityAge = 'min_entity_age';
    case RegionCovered = 'region';
}
