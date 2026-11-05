<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum SignInMethod: string
{
    case Password = 'password';
    case EmailVerificationCode = 'otp';
    case SignInCode = 'sign_in_code';
    case Totp = 'totp';
    case RecoveryCode = 'recovery_code';
    case TotpEnrollment = 'totp_enrollment';
    case Invitation = 'invitation';
}
