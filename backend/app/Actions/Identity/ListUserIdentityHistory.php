<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserIdentityChange;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListUserIdentityHistory
{
    private const int PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, UserIdentityChange>
     */
    public function handle(User $user): LengthAwarePaginator
    {
        return UserIdentityChange::query()
            ->with(['request', 'requester', 'approver'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }
}
