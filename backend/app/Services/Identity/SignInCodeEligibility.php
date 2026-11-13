<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Identity\EntityAccounts;
use App\Enums\Identity\EntityStanding;
use App\Enums\Identity\SignInCodeVerdict;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;

final readonly class SignInCodeEligibility
{
    public function __construct(private EntityAccounts $entityAccounts, private OtpIssueThrottle $throttle) {}

    public function of(?User $user): SignInCodeVerdict
    {
        if (! $user instanceof User) {
            return SignInCodeVerdict::Unknown;
        }

        if (! $user->mayUseSignInCode()) {
            return SignInCodeVerdict::NotEntity;
        }

        return $this->verdictForEntity($user, $this->entityAccounts->standingOf($user));
    }

    private function verdictForEntity(User $user, EntityStanding $standing): SignInCodeVerdict
    {
        return match (true) {
            $user->isLocked() => SignInCodeVerdict::Locked,
            $user->status !== UserStatus::Active, $standing->blocksSignIn() => SignInCodeVerdict::Inactive,
            ! $this->throttle->allows($user) => SignInCodeVerdict::Throttled,
            $standing === EntityStanding::PendingVerification => SignInCodeVerdict::RegistrationCode,
            default => SignInCodeVerdict::SignInCode,
        };
    }
}
