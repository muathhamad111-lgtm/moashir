<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOwnIdentityChanges
{
    private const int PER_PAGE = 10;

    /**
     * @return LengthAwarePaginator<int, IdentityChangeRequest>
     */
    public function handle(User $user): LengthAwarePaginator
    {
        return IdentityChangeRequest::query()->where('user_id', $user->id)->latest('id')->paginate(self::PER_PAGE);
    }
}
