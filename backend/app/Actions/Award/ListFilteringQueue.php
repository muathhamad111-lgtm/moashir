<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\FilteringQueueFilters;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Services\Award\FilteringQueueQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListFilteringQueue
{
    public const array SORTABLE = ['initial_percent', '-initial_percent', 'submitted_at', '-submitted_at'];

    private const array RELATIONS = ['entity', 'region', 'currentResult', 'awardCycle', 'latestFilteringDecision.decidedBy'];

    public function __construct(private FilteringQueueQuery $queue) {}

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function handle(AwardCycle $cycle, FilteringQueueFilters $filters): LengthAwarePaginator
    {
        $direction = str_starts_with($filters->sort, '-') ? 'desc' : 'asc';
        $query = $this->queue->filtered($cycle, $filters)
            ->select('assessments.*')
            ->with(self::RELATIONS)
            ->leftJoin('assessment_result_snapshots as initial_result', 'initial_result.id', '=', 'assessments.current_result_snapshot_id');

        ltrim($filters->sort, '-') === 'initial_percent'
            ? $query->orderBy('initial_result.percent', $direction)
            : $query->orderBy('assessments.submitted_at', $direction);

        return $query->orderBy('assessments.id')->paginate($filters->perPage);
    }
}
