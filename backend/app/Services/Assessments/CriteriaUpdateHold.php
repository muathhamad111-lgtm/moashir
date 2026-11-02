<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Entities\EntityRestoreStep;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ScalarValue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class CriteriaUpdateHold implements EntityRestoreStep
{
    private const string COLUMN = 'criteria_update_pending_since';

    private const string REDISPATCHES = 'criteria_update_redispatches';

    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    public function onRestore(Entity $entity): void
    {
        $this->openRequestsOf($entity->id)->toBase()->update([self::COLUMN => Date::now(), self::REDISPATCHES => 0]);
    }

    /**
     * @return list<int>
     */
    public function draftIdsToSettle(int $entityId): array
    {
        return array_values($this->freezeGuard->excludeFrozen($this->openRequestsOf($entityId))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }

    public function release(int $assessmentId): void
    {
        Assessment::query()->whereKey($assessmentId)->toBase()->update([self::COLUMN => null, self::REDISPATCHES => 0]);
    }

    /**
     * @return Collection<int, Assessment>
     */
    public function lockHeldOf(int $entityId): Collection
    {
        return Assessment::query()
            ->ownedBy($entityId)
            ->whereNotNull(self::COLUMN)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return list<int>
     */
    public function staleEntityIds(CarbonInterface $heldBefore): array
    {
        return array_values($this->freezeGuard->excludeFrozen(Assessment::query()->where(self::COLUMN, '<', $heldBefore))
            ->distinct()
            ->orderBy('entity_id')
            ->pluck('entity_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }

    /**
     * @param  Collection<int, Assessment>  $held
     */
    public function countRedispatch(Collection $held): int
    {
        $ids = $held->modelKeys();
        Assessment::query()->whereKey($ids)->toBase()->increment(self::REDISPATCHES);

        return ScalarValue::int(Assessment::query()->whereKey($ids)->max(self::REDISPATCHES));
    }

    /**
     * @return Builder<Assessment>
     */
    private function openRequestsOf(int $entityId): Builder
    {
        return Assessment::query()->followingCriteriaEdits()->ownedBy($entityId);
    }
}
