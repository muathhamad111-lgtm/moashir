<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\FilteringQueueFilters;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Award\AwardCycle;
use App\Services\Award\FilteringQueueQuery;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class SummarizeFilteringQueue
{
    public function __construct(private FilteringQueueQuery $queue) {}

    /**
     * @return array{disqualification_score: string, counts: array<string, int>, below_threshold: int}
     */
    public function handle(AwardCycle $cycle, FilteringQueueFilters $filters): array
    {
        $counts = $this->queue->filtered($cycle, $filters->withoutStatus())->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $byStatus = [];

        foreach (AdvancedAssessmentStatus::filteringStates() as $status) {
            $byStatus[$status->value] = ScalarValue::int($counts->get($status->value));
        }

        $byStatus[AdvancedAssessmentStatus::QualifiedForCommittee->value] = array_sum(array_map(
            static fn (AdvancedAssessmentStatus $status): int => ScalarValue::int($counts->get($status->value)),
            AdvancedAssessmentStatus::qualifiedStates(),
        ));

        $below = $this->queue->filtered($cycle, $filters->withoutStatus())
            ->where('status', AdvancedAssessmentStatus::AwaitingFiltering)
            ->whereHas('currentResult', static fn (Builder $snapshot): Builder => $snapshot->where('percent', '<', $cycle->disqualification_score->toDecimal()))
            ->count();

        return ['disqualification_score' => $cycle->disqualification_score->toDisplay(), 'counts' => $byStatus, 'below_threshold' => $below];
    }
}
