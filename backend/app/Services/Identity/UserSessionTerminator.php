<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserSession;
use App\Support\ScalarValue;

final class UserSessionTerminator
{
    public function terminateAll(User $user): int
    {
        return ScalarValue::int($user->sessions()->delete());
    }

    public function terminateAllExcept(User $user, string $keptSessionId): int
    {
        return ScalarValue::int($user->sessions()->whereKeyNot($keptSessionId)->delete());
    }

    public function terminate(UserSession $session): void
    {
        $session->delete();
    }
}
