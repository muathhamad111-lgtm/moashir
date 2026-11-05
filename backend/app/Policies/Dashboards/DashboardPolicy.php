<?php

declare(strict_types=1);

namespace App\Policies\Dashboards;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class DashboardPolicy
{
    public function view(User $user): bool
    {
        return $user->can(PermissionKey::DashboardView->value);
    }
}
