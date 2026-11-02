<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum CommitteeVoidReason: string
{
    case Excluded = 'excluded';
    case Replaced = 'replaced';
    case Removed = 'removed';
    case Withdrawn = 'withdrawn';
}
