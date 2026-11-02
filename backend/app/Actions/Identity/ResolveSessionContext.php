<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Contracts\Identity\CommitteeAppointmentsReader;
use App\Contracts\Identity\EntityAccounts;
use App\Data\Identity\SessionContext;
use App\Enums\Identity\PermissionKey;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Services\Identity\SessionClock;
use App\Services\Identity\SessionTimeouts;
use Illuminate\Database\Eloquent\Collection;

final readonly class ResolveSessionContext
{
    public function __construct(
        private SessionClock $clock,
        private SessionTimeouts $timeouts,
        private EntityAccounts $entityAccounts,
        private CommitteeAppointmentsReader $committeeAppointments,
    ) {}

    public function handle(User $user): SessionContext
    {
        $user->loadMissing('roles.permissions');
        $signedInAt = $this->clock->signedInAt();

        return new SessionContext(
            $user,
            $user->roles->sortBy('dashboard_priority')->values(),
            $this->effectivePermissions($user),
            $signedInAt > 0 ? $this->timeouts->absoluteExpiry($signedInAt)->toImmutable() : null,
            $this->timeouts->idleMinutesFor($user),
            $this->entityAccounts->sessionSummaryOf($user),
            $this->committeeAppointments->of($user),
        );
    }

    /**
     * @return list<string>
     */
    private function effectivePermissions(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return PermissionKey::values();
        }

        $names = $user->roles
            ->flatMap(static fn (Role $role): Collection => $role->permissions)
            ->map(static fn (Permission $permission): string => $permission->name)
            ->unique()
            ->all();
        sort($names);

        return $names;
    }
}
