<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\LoginStep;
use App\Models\Identity\User;

final readonly class LoginOutcome
{
    private function __construct(
        public LoginStep $step,
        public ?User $user = null,
        public ?OtpChallenge $otp = null,
    ) {}

    public static function authenticated(User $user): self
    {
        return new self(LoginStep::Authenticated, $user);
    }

    public static function mfaChallenge(): self
    {
        return new self(LoginStep::MfaChallenge);
    }

    public static function mfaEnrollment(): self
    {
        return new self(LoginStep::MfaEnrollment);
    }

    public static function otpRequired(OtpChallenge $otp): self
    {
        return new self(LoginStep::OtpRequired, null, $otp);
    }
}
