<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Data\Assessments\RequestQueueFilters;
use App\Data\Assessments\RequestQueueRow;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\RequestQueueQuery;
use App\Services\Assessments\SubmissionProgressReader;
use App\Services\Periods\PeriodLocator;
use App\Support\Certificates\EntityLevelQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListRequestQueue
{
    public const array SORTABLE = ['submitted_at', '-submitted_at', 'due_at', '-due_at', 'status', '-status', 'created_at', '-created_at'];

    public const array RELATIONS = ['entity.'.EntityLevelQuery::RELATION, 'entity.'.EntityLevelQuery::DISPLAY_RELATION, 'currentResult', 'latestAssignment.assessor', 'openCompletionRequest', 'payment.receipt', 'awardCycle', 'region', 'period'];

    public function __construct(private RequestQueueQuery $queue, private SubmissionProgressReader $progress, private PeriodLocator $locator) {}

    /**
     * @return LengthAwarePaginator<int, RequestQueueRow>
     */
    public function handle(RequestQueueFilters $filters): LengthAwarePaginator
    {
        $query = $this->queue->filtered($filters)->withEntityActivity()->withReassignment()->with(self::RELATIONS);
        $this->order($query, $filters->sort);
        $page = $query->orderByDesc('id')->paginate($filters->perPage);
        $progress = $this->progress->readMany(array_values(array_filter($page->items(), $this->isPreparing(...))));
        $periodEndsOn = $this->locator->current()?->ends_on;

        return $page->through(static fn (Assessment $assessment): RequestQueueRow => new RequestQueueRow($assessment, $progress[$assessment->id] ?? null, $periodEndsOn));
    }

    /**
     * A draft has no submission or due date yet: it sorts by the date it was opened, and after the dated rows by due date.
     *
     * @param  Builder<Assessment>  $query
     */
    private function order(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        match (ltrim($sort, '-')) {
            'submitted_at' => $query->orderByRaw('coalesce(assessments.submitted_at, assessments.created_at) '.$direction),
            'due_at' => $query->orderByRaw('assessments.due_at '.$direction.' nulls last'),
            default => $query->orderBy('assessments.'.ltrim($sort, '-'), $direction),
        };
    }

    private function isPreparing(Assessment $assessment): bool
    {
        return $assessment->status instanceof ReviewedAssessmentState && $assessment->status->isPreparedByEntity();
    }
}
