<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Actions\Identity\VerifyOtpCode;
use App\Enums\Identity\OtpPurpose;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\OtpCode;

final readonly class SignInCodeVerifier
{
    public function __construct(
        private SignInOtpChallenges $challenges,
        private VerifyOtpCode $verifyOtp,
        private SignInGuard $guard,
        private AccountLockout $lockout,
    ) {}

    public function verify(string $challengeId, string $code): OtpCode
    {
        $challenge = $this->challenges->find($challengeId);

        if (! $challenge instanceof OtpCode) {
            return $this->verifyOtp->handle($challengeId, $code, OtpPurpose::Login);
        }

        $this->guard->assertNotLocked($challenge->user);
        $verified = $this->verifyCounted($challenge, $code);
        $this->challenges->release($verified);

        return $verified;
    }

    private function verifyCounted(OtpCode $challenge, string $code): OtpCode
    {
        try {
            return $this->verifyOtp->handle($challenge->challenge_id, $code, $challenge->purpose);
        } catch (OtpRejected $rejection) {
            if ($rejection->isWrongCode() && $this->lockout->recordFailure($challenge->user)) {
                throw $this->lockout->rejection();
            }

            throw $rejection;
        }
    }
}
