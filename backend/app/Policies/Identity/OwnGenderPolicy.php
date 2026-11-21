<?php

declare(strict_types=1);

namespace App\Policies\Identity;

use App\Models\Identity\User;

/**
 * Gender is collected for staff accounts only (operator decision, 2026-11-21).
 */
final class OwnGenderPolicy
{
    public function record(User $user): bool
    {
        return ! $user->isEntityUser();
    }
}
