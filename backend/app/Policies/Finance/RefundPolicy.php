<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class RefundPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::FinanceRefundsManage->value) || $actor->can(PermissionKey::FinanceOperationsView->value);
    }

    public function view(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function downloadEvidence(User $actor): bool
    {
        return $this->viewAny($actor);
    }
}
