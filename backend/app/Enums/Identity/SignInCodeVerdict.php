<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum SignInCodeVerdict: string
{
    case SignInCode = 'sign_in_code';
    case RegistrationCode = 'registration_code';
    case Unknown = 'unknown';
    case NotEntity = 'not_entity';
    case Inactive = 'inactive';
    case Locked = 'locked';
    case Throttled = 'throttled';

    public function sendsCode(): bool
    {
        return $this === self::SignInCode || $this === self::RegistrationCode;
    }
}
