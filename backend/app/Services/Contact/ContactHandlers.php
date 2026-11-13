<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

final class ContactHandlers
{
    /**
     * @return Builder<User>
     */
    public function query(): Builder
    {
        return User::query()
            ->permission(PermissionKey::ContactMessagesManage->value)
            ->staff()
            ->withoutSuperAdmins()
            ->where('status', UserStatus::Active);
    }

    public function find(int $userId): ?User
    {
        return $this->query()->whereKey($userId)->first();
    }
}
