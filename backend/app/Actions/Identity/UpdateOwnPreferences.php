<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\PreferenceChanges;
use App\Models\Identity\User;

final class UpdateOwnPreferences
{
    public function handle(User $user, PreferenceChanges $changes): User
    {
        $user->fill(array_filter([
            'locale' => $changes->locale,
            'theme' => $changes->theme,
        ], static fn (mixed $value): bool => $value !== null))->save();

        return $user;
    }
}
