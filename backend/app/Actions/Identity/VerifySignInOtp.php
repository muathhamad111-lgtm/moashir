<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Contracts\Identity\EntityAccounts;
use App\Data\Identity\LoginOutcome;
use App\Enums\Identity\OtpPurpose;
use App\Services\Identity\SignInGuard;
use Illuminate\Support\Facades\Date;

final readonly class VerifySignInOtp
{
    public function __construct(
        private VerifyOtpCode $verifyOtp,
        private SignInGuard $guard,
        private EntityAccounts $entityAccounts,
        private CompleteSignIn $completeSignIn,
    ) {}

    public function handle(string $challengeId, string $code): LoginOutcome
    {
        $user = $this->verifyOtp->handle($challengeId, $code, OtpPurpose::Login)->user;

        $this->guard->assertCanSignIn($user);

        if ($this->guard->awaitsEntityVerification($user)) {
            $this->entityAccounts->confirmVerification($user);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => Date::now()])->save();
        }

        return $this->completeSignIn->handle($user, 'otp');
    }
}
