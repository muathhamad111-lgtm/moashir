<?php

declare(strict_types=1);

namespace App\Policies\Audit;

use App\Enums\Identity\PermissionKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Services\Audit\AuditVisibility;
use Illuminate\Auth\Access\Response;

final readonly class AuditPolicy
{
    public function __construct(private AuditVisibility $visibility) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionKey::AuditView->value);
    }

    public function view(User $user, Audit $audit): Response
    {
        if (! $user->can(PermissionKey::AuditView->value)) {
            return Response::deny();
        }

        return $this->visibility->isHiddenFrom($user, $audit) ? Response::denyAsNotFound() : Response::allow();
    }

    public function export(User $user): bool
    {
        return $user->can(PermissionKey::AuditExport->value);
    }
}
