<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Identity\RoleKey;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Models\Platform\DemoRecord;
use Illuminate\Database\Eloquent\Collection;

final class DemoRecords
{
    public function remember(DemoRecordKind $kind, int $recordId): void
    {
        DemoRecord::query()->create(['kind' => $kind, 'record_id' => $recordId]);
    }

    public function isInstalled(): bool
    {
        return DemoRecord::query()->exists();
    }

    public function countEntitiesNotCreatedByTheDemo(): int
    {
        return Entity::withTrashed()
            ->whereNotIn('id', DemoRecord::query()->where('kind', DemoRecordKind::Entity)->select('record_id'))
            ->count();
    }

    public function staffWithRole(RoleKey $role): ?User
    {
        return $this->users()->first(static fn (User $user): bool => $user->roles->contains('name', $role->value));
    }

    /**
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return User::query()
            ->whereIn('id', DemoRecord::query()->where('kind', DemoRecordKind::User)->select('record_id'))
            ->with('roles')
            ->orderBy('id')
            ->get();
    }
}
