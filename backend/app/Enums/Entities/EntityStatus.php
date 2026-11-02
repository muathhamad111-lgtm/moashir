<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum EntityStatus: string
{
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Deactivated = 'deactivated';
}
