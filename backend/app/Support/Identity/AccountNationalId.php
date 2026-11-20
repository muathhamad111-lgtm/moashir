<?php

declare(strict_types=1);

namespace App\Support\Identity;

use App\Models\Identity\User;
use App\Support\ValueObjects\NationalId;

final class AccountNationalId
{
    public static function of(User $user): ?NationalId
    {
        return $user->national_id === null ? null : NationalId::tryFrom($user->national_id);
    }

    public static function isRecorded(User $user): bool
    {
        return $user->national_id_index !== null;
    }
}
