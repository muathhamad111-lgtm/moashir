<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum PendingAuthenticationStage: string
{
    case MfaChallenge = 'mfa_challenge';
    case MfaEnrollment = 'mfa_enrollment';
}
