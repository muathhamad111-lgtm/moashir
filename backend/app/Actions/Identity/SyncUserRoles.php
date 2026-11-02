<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\MfaRequirement;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SyncUserRoles
{
    public function __construct(private MfaRequirement $mfaRequirement, private AuditRecorder $audit) {}

    /**
     * @param  list<RoleKey>  $roles
     */
    public function handle(User $actor, User $target, array $roles): void
    {
        $before = $this->roleValues($target->roleKeys());

        DB::transaction(function () use ($actor, $target, $roles, $before): void {
            $this->replaceRoles($actor, $target, $roles);
            $target->unsetRelation('roles');
            $target->forgetCachedPermissions();
            $target->forceFill(['mfa_required' => $this->mfaRequirement->isRequiredFor($roles)])->save();

            $after = $this->roleValues($roles);

            if ($before !== $after) {
                $this->audit->record(new AuditEntry(AuditEvent::UserRolesChanged, $target, ['roles' => $before], ['roles' => $after]));
            }
        });
    }

    /**
     * @param  list<RoleKey>  $roles
     */
    private function replaceRoles(User $actor, User $target, array $roles): void
    {
        $wantedIds = $this->ids(Role::query()->whereIn('name', $this->roleValues($roles))->pluck('id')->all());
        $currentIds = $this->ids($target->roles()->pluck('roles.id')->all());

        $target->roles()->detach(array_values(array_diff($currentIds, $wantedIds)));
        $target->roles()->attach(array_values(array_diff($wantedIds, $currentIds)), [
            'assigned_by_user_id' => $actor->id,
            'assigned_at' => Date::now(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        return array_values(array_map(ScalarValue::int(...), $values));
    }

    /**
     * @param  list<RoleKey>  $roles
     * @return list<string>
     */
    private function roleValues(array $roles): array
    {
        $values = array_map(static fn (RoleKey $role): string => $role->value, $roles);
        sort($values);

        return $values;
    }
}
