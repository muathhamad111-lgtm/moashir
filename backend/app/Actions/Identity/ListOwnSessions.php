<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserSession;
use Illuminate\Database\Eloquent\Collection;

final class ListOwnSessions
{
    /**
     * @return Collection<int, UserSession>
     */
    public function handle(User $user): Collection
    {
        return $user->sessions()->orderByDesc('last_activity')->get();
    }
}
