<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Identity\EntityAccounts;
use App\Enums\Identity\EntityStanding;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final readonly class SignInGuard
{
    public function __construct(private EntityAccounts $entityAccounts) {}

    public function assertCanSignIn(User $user): void
    {
        $this->assertNotLocked($user);

        if ($user->status === UserStatus::Suspended) {
            throw AuthenticationRejected::accountDeactivated();
        }

        if ($user->status !== UserStatus::Active) {
            throw AuthenticationRejected::invalidCredentials();
        }

        match ($this->entityAccounts->standingOf($user)) {
            EntityStanding::PendingReview => throw AuthenticationRejected::entityPendingReview(),
            EntityStanding::Rejected => throw AuthenticationRejected::entityRejected(),
            EntityStanding::Deactivated => throw AuthenticationRejected::accountDeactivated(),
            EntityStanding::Closed => throw AuthenticationRejected::invalidCredentials(),
            default => null,
        };
    }

    public function assertNotLocked(User $user): void
    {
        if ($user->isLocked() && $user->locked_until !== null) {
            throw AuthenticationRejected::accountLocked((int) Date::now()->diffInSeconds($user->locked_until, true));
        }
    }

    /**
     * An entity account gets no session until its owner proves the official e-mail: either the
     * registration predates the e-mail check on the form, or staff approved it before the address
     * was confirmed.
     */
    public function awaitsEmailConfirmation(User $user): bool
    {
        if ($user->entity_id === null) {
            return false;
        }

        return $user->email_verified_at === null
            || $this->entityAccounts->standingOf($user) === EntityStanding::PendingVerification;
    }
}
