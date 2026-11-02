<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum CommitteeDiscussionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
