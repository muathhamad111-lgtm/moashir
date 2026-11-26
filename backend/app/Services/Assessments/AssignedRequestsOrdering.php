<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AssignedRequestsSort;
use App\Enums\Assessments\AssignedRequestsTab;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Support\Dashboards\AggregateRow;
use App\Support\ListOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders an assessor's assigned requests by the chosen column, the request id breaking ties.
 */
final class AssignedRequestsOrdering
{
    /**
     * @param  Builder<Assessment>  $query
     */
    public function apply(Builder $query, ListOrder $order, int $assessorId): void
    {
        $direction = $order->direction();

        match (AssignedRequestsSort::from($order->column)) {
            AssignedRequestsSort::DueAt => $query->orderByRaw($order->descending ? 'due_at desc nulls last' : 'due_at asc nulls last'),
            AssignedRequestsSort::AssignedAt => $query->orderBy($this->assignedAt($assessorId), $direction),
            AssignedRequestsSort::Status => $this->byStatusGroup($query, $order),
        };

        $query->orderBy('id', $direction);
    }

    /**
     * When this assessor's latest assignment on the request was made.
     *
     * @return Builder<AssessmentAssignment>
     */
    private function assignedAt(int $assessorId): Builder
    {
        return AssessmentAssignment::query()
            ->selectRaw('max(created_at)')
            ->whereColumn('assessment_assignments.assessment_id', 'assessments.id')
            ->where('assessor_user_id', $assessorId);
    }

    /**
     * New, then in progress, then completed: the workflow order of the statuses.
     *
     * @param  Builder<Assessment>  $query
     */
    private function byStatusGroup(Builder $query, ListOrder $order): void
    {
        $statuses = array_merge(...array_map(static fn (AssignedRequestsTab $group): array => $group->statusValues(), AssignedRequestsTab::cases()));
        $position = 'array_position(array['.AggregateRow::placeholders($statuses).']::text[], status::text)';

        $query->orderByRaw($order->descending ? $position.' desc' : $position.' asc', $statuses);
    }
}
