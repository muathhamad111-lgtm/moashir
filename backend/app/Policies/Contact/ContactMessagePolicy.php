<?php

declare(strict_types=1);

namespace App\Policies\Contact;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class ContactMessagePolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->handles($actor);
    }

    public function view(User $actor): bool
    {
        return $this->handles($actor);
    }

    public function manage(User $actor): bool
    {
        return $this->handles($actor);
    }

    public function listAssignees(User $actor): bool
    {
        return $this->handles($actor);
    }

    public function viewOwn(): bool
    {
        return true;
    }

    public function managePersonalData(User $actor): bool
    {
        return $actor->can(PermissionKey::PrivacyRequestsManage->value);
    }

    private function handles(User $actor): bool
    {
        return $actor->can(PermissionKey::ContactMessagesManage->value);
    }
}
