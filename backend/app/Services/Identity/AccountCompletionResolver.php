<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\AccountCompletion;
use App\Data\Identity\NationalIdStatus;
use App\Enums\Identity\IdentityChangeStatus;
use App\Enums\Identity\NationalIdState;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Support\Identity\AccountNationalId;

final readonly class AccountCompletionResolver
{
    public function __construct(private NationalIdRequirement $requirement) {}

    public function of(User $user): AccountCompletion
    {
        return new AccountCompletion($this->nationalIdOf($user), ! $user->isEntityUser() && $user->gender === null);
    }

    private function nationalIdOf(User $user): NationalIdStatus
    {
        $required = $this->requirement->isRequiredFor($user);
        $masked = AccountNationalId::of($user)?->masked();

        $state = match (true) {
            $masked !== null => NationalIdState::Provided,
            $this->hasPendingNationalId($user) => NationalIdState::Pending,
            $required => NationalIdState::Missing,
            default => NationalIdState::NotRequired,
        };

        return new NationalIdStatus($required, $masked, $state);
    }

    private function hasPendingNationalId(User $user): bool
    {
        return IdentityChangeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', IdentityChangeStatus::Pending)
            ->whereNotNull('new_national_id')
            ->exists();
    }
}
