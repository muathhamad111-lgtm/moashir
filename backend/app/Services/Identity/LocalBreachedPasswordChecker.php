<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\BreachedPasswordChecker;
use App\Models\Identity\BreachedPassword;
use App\Support\ValueObjects\PasswordDigest;

final class LocalBreachedPasswordChecker implements BreachedPasswordChecker
{
    public function isBreached(string $password): bool
    {
        return BreachedPassword::query()->whereKey(PasswordDigest::ofPassword($password)->value)->exists();
    }
}
