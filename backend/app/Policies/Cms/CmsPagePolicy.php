<?php

declare(strict_types=1);

namespace App\Policies\Cms;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class CmsPagePolicy
{
    public function manage(User $user): bool
    {
        return $user->can(PermissionKey::ContentHomeManage->value);
    }
}
