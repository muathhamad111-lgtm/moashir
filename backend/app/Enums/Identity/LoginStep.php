<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum LoginStep: string
{
    case Authenticated = 'authenticated';
    case MfaChallenge = 'mfa_challenge';
    case MfaEnrollment = 'mfa_enrollment';
    case OtpRequired = 'otp_required';
}
