<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Identity\RoleKey;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Models\Platform\DemoRecord;
use Illuminate\Database\Eloquent\Builder;
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

    public function hasInsightsCases(): bool
    {
        return DemoRecord::query()->where('kind', DemoRecordKind::InsightsAssessment)->exists();
    }

    public function countEntitiesNotCreatedByTheDemo(): int
    {
        return Entity::withTrashed()
            ->whereNotIn('id', $this->recordIdsOf(DemoRecordKind::Entity))
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
            ->whereIn('id', $this->recordIdsOf(DemoRecordKind::User))
            ->with('roles')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<User>
     */
    public function entityOwners(): array
    {
        return array_values(User::query()
            ->whereIn('id', $this->recordIdsOf(DemoRecordKind::User))
            ->whereIn('entity_id', Entity::query()->whereIn('id', $this->recordIdsOf(DemoRecordKind::Entity))->select('id'))
            ->orderBy('entity_id')
            ->get()
            ->all());
    }

    /**
     * @return Builder<DemoRecord>
     */
    private function recordIdsOf(DemoRecordKind $kind): Builder
    {
        return DemoRecord::query()->where('kind', $kind)->select('record_id');
    }
}
