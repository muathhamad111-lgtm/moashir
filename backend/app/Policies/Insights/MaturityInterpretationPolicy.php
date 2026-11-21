<?php

declare(strict_types=1);

namespace App\Policies\Insights;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class MaturityInterpretationPolicy
{
    public function manage(User $user): bool
    {
        return $user->can(PermissionKey::ContentInsightsManage->value);
    }
}
