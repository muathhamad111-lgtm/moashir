<?php

declare(strict_types=1);

namespace App\Policies\Periods;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class AssessmentPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionKey::RequestsQueueView->value)
            || $user->can(PermissionKey::RequestsAssignedView->value)
            || $user->can(PermissionKey::SettingsAssessmentManage->value);
    }
}
