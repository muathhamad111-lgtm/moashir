<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Contracts\Identity\EntityAccounts;
use App\Data\Identity\LoginOutcome;
use App\Enums\Identity\OtpPurpose;
use App\Enums\Identity\SignInMethod;
use App\Models\Identity\User;
use App\Services\Identity\SignInCodeVerifier;
use App\Services\Identity\SignInGuard;
use Illuminate\Support\Facades\Date;

final readonly class VerifySignInOtp
{
    public function __construct(
        private SignInCodeVerifier $verifier,
        private SignInGuard $guard,
        private EntityAccounts $entityAccounts,
        private CompleteSignIn $completeSignIn,
    ) {}

    public function handle(string $challengeId, string $code): LoginOutcome
    {
        $verified = $this->verifier->verify($challengeId, $code);
        $user = $verified->user;

        $this->guard->assertCanSignIn($user);

        if ($verified->purpose === OtpPurpose::LoginSecondFactor) {
            return $this->completeSignIn->handle($user, SignInMethod::EmailSecondFactor);
        }

        $this->confirmEmail($user);

        return $this->completeSignIn->handle($user, SignInMethod::EmailVerificationCode);
    }

    private function confirmEmail(User $user): void
    {
        if ($this->guard->awaitsEntityVerification($user)) {
            $this->entityAccounts->confirmVerification($user);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => Date::now()])->save();
        }
    }
}
