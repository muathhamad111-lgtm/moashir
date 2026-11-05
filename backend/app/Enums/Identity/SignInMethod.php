<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum SignInMethod: string
{
    case Password = 'password';
    case EmailVerificationCode = 'otp';
    case EmailSecondFactor = 'email_second_factor';
    case Totp = 'totp';
    case RecoveryCode = 'recovery_code';
    case TotpEnrollment = 'totp_enrollment';
    case Invitation = 'invitation';

    public function hasProvenSecondFactor(): bool
    {
        return $this !== self::Password;
    }
}
