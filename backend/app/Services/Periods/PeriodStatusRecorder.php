<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Periods\ResolvedPeriodState;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\EntityStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Assessments\EntityPeriodStatus;
use App\Models\Entities\Entity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class PeriodStatusRecorder
{
    private const int CHUNK = 500;

    public function __construct(private PeriodStateResolver $resolver) {}

    public function recordEnded(AssessmentPeriod $period, int $largestReminderDays): void
    {
        $this->evaluatedEntities($period)->chunkById(self::CHUNK, function (Collection $entities) use ($period, $largestReminderDays): void {
            $this->record($period, $entities, $largestReminderDays);
        });
    }

    /**
     * @param  Collection<int, Entity>  $entities
     */
    public function record(AssessmentPeriod $period, Collection $entities, int $largestReminderDays): void
    {
        $requests = Assessment::query()
            ->ofPath(AssessmentPath::Accredited)
            ->where('period_id', $period->id)
            ->whereIn('entity_id', $entities->modelKeys())
            ->latest('id')
            ->get()
            ->groupBy('entity_id');
        $now = Date::now();
        $rows = [];

        foreach ($entities as $entity) {
            $resolved = $this->resolver->resolve($entity, $period, $requests->get($entity->id, []), true, $largestReminderDays);
            $rows[] = $this->rowOf($entity, $period, $resolved, $now);
        }

        EntityPeriodStatus::query()->upsert($rows, ['entity_id', 'assessment_period_id'], ['status', 'assessment_id', 'decided_at', 'updated_at']);
    }

    /**
     * @return Builder<Entity>
     */
    private function evaluatedEntities(AssessmentPeriod $period): Builder
    {
        return Entity::withTrashed()
            ->whereNotNull('verified_at')
            ->where('verified_at', '<', $period->ends_at)
            ->whereNull('anonymized_at')
            ->where(static fn (Builder $inAudience): Builder => $inAudience->where('status', EntityStatus::Active)->orWhereNotNull('deleted_at'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rowOf(Entity $entity, AssessmentPeriod $period, ResolvedPeriodState $resolved, CarbonInterface $now): array
    {
        return [
            'entity_id' => $entity->id,
            'assessment_period_id' => $period->id,
            'status' => $resolved->state->value,
            'assessment_id' => $resolved->request?->id,
            'decided_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
