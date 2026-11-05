<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum OtpPurpose: string
{
    case Login = 'login';
    case LoginSecondFactor = 'login_second_factor';
    case VerifyEmail = 'verify_email';
    case VerifyPhone = 'verify_phone';
    case PasswordReset = 'password_reset';
    case EmailChange = 'email_change';

    public function countsTowardLockout(): bool
    {
        return in_array($this, [self::Login, self::LoginSecondFactor], true);
    }

    /**
     * @return list<self>
     */
    public static function signIn(): array
    {
        return [self::Login, self::LoginSecondFactor];
    }
}
