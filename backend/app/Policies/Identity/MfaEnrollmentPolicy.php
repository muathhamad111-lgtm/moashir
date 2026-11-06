<?php

declare(strict_types=1);

namespace App\Policies\Identity;

use App\Models\Identity\User;

final class MfaEnrollmentPolicy
{
    public function enroll(User $user): bool
    {
        return ! $user->isEntityUser();
    }
}
