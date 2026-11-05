<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\LoginOutcome;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Enums\Identity\PendingAuthenticationStage;
use App\Enums\Identity\SignInMethod;
use App\Models\Identity\User;
use App\Services\Identity\EntitySecondFactor;
use App\Services\Identity\PendingAuthentication;
use App\Services\Identity\SignInGuard;

final readonly class CompleteSignIn
{
    public function __construct(
        private EstablishSession $establishSession,
        private PendingAuthentication $pending,
        private SignInGuard $guard,
        private IssueOtpChallenge $issueChallenge,
        private EntitySecondFactor $entitySecondFactor,
    ) {}

    public function handle(User $user, SignInMethod $method): LoginOutcome
    {
        if ($this->guard->awaitsEntityVerification($user)) {
            return LoginOutcome::otpRequired($this->issueChallenge->handle(new OtpDelivery($user, OtpChannel::Email, OtpPurpose::Login)));
        }

        if ($user->hasConfirmedTotp()) {
            $this->pending->begin($user, PendingAuthenticationStage::MfaChallenge);

            return LoginOutcome::mfaChallenge();
        }

        if ($user->requiresMfa()) {
            $this->pending->begin($user, PendingAuthenticationStage::MfaEnrollment);

            return LoginOutcome::mfaEnrollment();
        }

        if (! $method->hasProvenSecondFactor() && $this->entitySecondFactor->appliesTo($user)) {
            return $this->challengeByEmail($user);
        }

        return LoginOutcome::authenticated($this->establishSession->handle($user, $method));
    }

    private function challengeByEmail(User $user): LoginOutcome
    {
        $this->pending->begin($user, PendingAuthenticationStage::EmailSecondFactor);

        return LoginOutcome::otpRequired($this->issueChallenge->handle(new OtpDelivery($user, OtpChannel::Email, OtpPurpose::LoginSecondFactor)));
    }
}
