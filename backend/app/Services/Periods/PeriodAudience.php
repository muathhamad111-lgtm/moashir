<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Enums\Entities\EntityStatus;
use App\Models\Entities\Entity;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

final class PeriodAudience
{
    /**
     * @return Builder<Entity>
     */
    public function entities(): Builder
    {
        return Entity::query()->where('status', EntityStatus::Active)->whereNotNull('verified_at');
    }

    /**
     * @return Builder<Entity>
     */
    public function withoutSubmissionIn(int $periodId): Builder
    {
        return $this->entities()->whereNotIn('id', static fn (QueryBuilder $submitted): QueryBuilder => $submitted
            ->select('entity_id')
            ->from('assessments')
            ->where('period_id', $periodId));
    }
}
