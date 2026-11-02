<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum OtpPurpose: string
{
    case Login = 'login';
    case VerifyEmail = 'verify_email';
    case VerifyPhone = 'verify_phone';
    case PasswordReset = 'password_reset';
    case EmailChange = 'email_change';
}
