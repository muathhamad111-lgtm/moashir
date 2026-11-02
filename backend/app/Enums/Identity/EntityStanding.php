<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum EntityStanding: string
{
    case NotAnEntity = 'not_an_entity';
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Closed = 'closed';

    public function blocksSignIn(): bool
    {
        return $this === self::Deactivated || $this === self::Closed;
    }
}
