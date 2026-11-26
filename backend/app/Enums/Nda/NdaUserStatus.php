<?php

declare(strict_types=1);

namespace App\Enums\Nda;

/**
 * Where a user stands against the current non-disclosure agreement.
 */
enum NdaUserStatus: string
{
    case Accepted = 'accepted';
    case Pending = 'pending';
    case OutOfScope = 'out_of_scope';
}
