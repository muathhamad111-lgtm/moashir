<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum DeltaComparison: string
{
    case Matched = 'matched';
    case NewInVersion = 'new_in_version';
    case NoPrevious = 'no_previous';
}
