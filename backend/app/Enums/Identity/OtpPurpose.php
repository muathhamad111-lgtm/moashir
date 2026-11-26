<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum OtpPurpose: string
{
    case Login = 'login';
    case SignInCode = 'sign_in_code';
    case VerifyEmail = 'verify_email';
    case VerifyPhone = 'verify_phone';
    case PasswordReset = 'password_reset';
    case EmailChange = 'email_change';

    public function maySendToUnverifiedPhone(): bool
    {
        return $this === self::VerifyPhone;
    }

    /**
     * @return list<self>
     */
    public static function signIn(): array
    {
        return [self::Login, self::SignInCode];
    }
}
