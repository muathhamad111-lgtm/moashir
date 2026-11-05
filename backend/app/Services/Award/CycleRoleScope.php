<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Identity\RequestPathScope;
use Illuminate\Auth\Access\Response;

final readonly class CycleRoleScope
{
    public function __construct(private CycleMembership $membership, private CycleRecordScope $records, private RequestPathScope $paths) {}

    /**
     * @param  list<RoleKey>  $globalRoles
     * @param  list<CycleMemberRole>  $memberRoles
     */
    public function allows(User $user, AwardCycle $cycle, PermissionKey $permission, array $globalRoles, array $memberRoles): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->entity_id !== null || ! $user->can($permission->value)) {
            return false;
        }

        $user->loadMissing('roles');

        if (array_any($globalRoles, static fn (RoleKey $role): bool => in_array($role, $user->roleKeys(), true)) && $this->paths->covers($user, AssessmentPath::Advanced)) {
            return true;
        }

        return $memberRoles !== [] && $this->membership->isMember($user->id, $cycle->id, ...$memberRoles);
    }

    public function respond(bool $allowed, User $user, AwardCycle $cycle, PermissionKey ...$permissions): Response
    {
        if ($allowed) {
            return Response::allow();
        }

        if ($permissions !== [] && ! array_any($permissions, static fn (PermissionKey $permission): bool => $user->can($permission->value))) {
            return Response::deny();
        }

        return $this->records->canView($user, $cycle) ? Response::deny() : Response::denyAsNotFound();
    }
}
