<?php

declare(strict_types=1);

namespace App\Enums\Periods;

enum PeriodTimeline: string
{
    case Past = 'past';
    case Current = 'current';
    case Upcoming = 'upcoming';
}
