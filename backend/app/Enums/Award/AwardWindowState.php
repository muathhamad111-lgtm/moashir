<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum AwardWindowState: string
{
    case Open = 'open';
    case Upcoming = 'upcoming';
    case Closed = 'closed';
    case None = 'none';
}
