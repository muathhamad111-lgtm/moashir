<?php

declare(strict_types=1);

namespace App\Policies\Integrations;

use App\Enums\Integrations\IntegrationKind;
use App\Models\Identity\User;

final class IntegrationSettingsPolicy
{
    public function viewOverview(User $user): bool
    {
        return array_any(IntegrationKind::cases(), fn (IntegrationKind $kind): bool => $this->manages($user, $kind));
    }

    public function managePayment(User $user): bool
    {
        return $this->manages($user, IntegrationKind::Payment);
    }

    public function manageSms(User $user): bool
    {
        return $this->manages($user, IntegrationKind::Sms);
    }

    public function manageEmail(User $user): bool
    {
        return $this->manages($user, IntegrationKind::Email);
    }

    public function manageAi(User $user): bool
    {
        return $this->manages($user, IntegrationKind::Ai);
    }

    private function manages(User $user, IntegrationKind $kind): bool
    {
        return $user->can($kind->permission()->value);
    }
}
