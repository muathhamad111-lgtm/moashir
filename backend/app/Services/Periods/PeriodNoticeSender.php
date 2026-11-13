<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Assessments\PeriodNotice;
use App\Models\Entities\Entity;
use App\Services\Entities\EntityNotifier;
use App\Support\ScalarValue;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class PeriodNoticeSender
{
    private const int CHUNK = 500;

    public function __construct(private EntityNotifier $notifier) {}

    /**
     * @param  Builder<Entity>  $audience
     * @param  Closure(Entity): EntityNotice  $noticeFor
     */
    public function send(AssessmentPeriod $period, NotificationCode $code, int $thresholdDays, Builder $audience, Closure $noticeFor): int
    {
        $sent = 0;

        $audience->chunkById(self::CHUNK, function (Collection $entities) use ($period, $code, $thresholdDays, $noticeFor, &$sent): void {
            $fresh = $this->claim($period, $code, $thresholdDays, $entities);
            $this->notifier->toEachOwner($fresh, $noticeFor);
            $sent += $fresh->count();
        });

        return $sent;
    }

    /**
     * @param  Collection<int, Entity>  $entities
     * @return Collection<int, Entity>
     */
    private function claim(AssessmentPeriod $period, NotificationCode $code, int $thresholdDays, Collection $entities): Collection
    {
        $already = PeriodNotice::query()
            ->where('assessment_period_id', $period->id)
            ->where('code', $code)
            ->where('threshold_days', $thresholdDays)
            ->whereIn('entity_id', $entities->modelKeys())
            ->pluck('entity_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all();
        $fresh = $entities->reject(static fn (Entity $entity): bool => in_array($entity->id, $already, true))->values();
        $now = Date::now();

        PeriodNotice::query()->insertOrIgnore($fresh->map(static fn (Entity $entity): array => [
            'assessment_period_id' => $period->id,
            'entity_id' => $entity->id,
            'code' => $code->value,
            'threshold_days' => $thresholdDays,
            'sent_at' => $now,
        ])->all());

        return $fresh;
    }
}
