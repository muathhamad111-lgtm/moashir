<?php

declare(strict_types=1);

namespace App\Enums\Nda;

enum NdaAcceptanceFilter: string
{
    case All = 'all';
    case Pending = 'pending';
    case Accepted = 'accepted';
}
