<?php

declare(strict_types=1);

namespace App\Policies\Kpis;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class KpiPolicy
{
    public function manage(User $user): bool
    {
        return $user->can(PermissionKey::ContentKpisManage->value);
    }
}
