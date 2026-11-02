<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum PrivacyRequestStatus: string
{
    case Open = 'open';
    case Fulfilled = 'fulfilled';
    case Rejected = 'rejected';
}
