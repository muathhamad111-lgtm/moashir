<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;

final class TransferReviewers
{
    public function activeCount(): int
    {
        return User::query()
            ->permission(PermissionKey::FinanceTransfersReview->value)
            ->staff()
            ->withoutSuperAdmins()
            ->where('status', UserStatus::Active)
            ->count();
    }
}
