<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Locked = 'locked';
    case Suspended = 'suspended';
    case Expired = 'expired';
}
