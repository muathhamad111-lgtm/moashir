<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\PendingAuthenticationStage;
use App\Models\Identity\User;
use App\Services\Identity\PendingAuthentication;
use App\Services\Identity\SignInGuard;
use App\Services\Identity\TotpVerifier;

final readonly class VerifyMfaChallenge
{
    public function __construct(
        private PendingAuthentication $pending,
        private SignInGuard $guard,
        private TotpVerifier $verifier,
        private RecordFailedMfa $recordFailure,
        private EstablishSession $establishSession,
    ) {}

    public function handle(?string $code, ?string $recoveryCode): User
    {
        $user = $this->pending->userFor(PendingAuthenticationStage::MfaChallenge);

        $this->guard->assertCanSignIn($user);

        $isValid = $code !== null
            ? $this->verifier->verifyCode($user, $code)
            : $this->verifier->consumeRecoveryCode($user, (string) $recoveryCode);

        if (! $isValid) {
            $this->recordFailure->handle($user);
        }

        return $this->establishSession->handle($user, $code !== null ? 'totp' : 'recovery_code');
    }
}
