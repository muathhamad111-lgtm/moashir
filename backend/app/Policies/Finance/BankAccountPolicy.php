<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class BankAccountPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->can(PermissionKey::SettingsPaymentManage->value);
    }
}
