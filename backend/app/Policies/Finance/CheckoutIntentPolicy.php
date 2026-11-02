<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class CheckoutIntentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::MessagingCampaignsSend->value);
    }
}
