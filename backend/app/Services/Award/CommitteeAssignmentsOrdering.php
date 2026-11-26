<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CommitteeAssignmentsSort;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Support\ListOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders a committee assessor's assignments by the chosen column, the assignment id breaking ties.
 */
final class CommitteeAssignmentsOrdering
{
    /** Not started, then in progress, then submitted. */
    private const array STATUS_ORDER = [CommitteeAssignmentStatus::NotStarted, CommitteeAssignmentStatus::InProgress, CommitteeAssignmentStatus::Completed];

    /**
     * @param  Builder<CommitteeAssignment>  $query
     */
    public function apply(Builder $query, ListOrder $order): void
    {
        $direction = $order->direction();

        match (CommitteeAssignmentsSort::from($order->column)) {
            CommitteeAssignmentsSort::DueAt => $query->orderBy(
                AwardCycle::query()->select('committee_end')->whereColumn('award_cycles.id', 'committee_assignments.award_cycle_id'),
                $direction,
            ),
            CommitteeAssignmentsSort::Status => $query->orderByRaw(
                $order->descending ? 'array_position(array[?, ?, ?]::text[], status::text) desc' : 'array_position(array[?, ?, ?]::text[], status::text) asc',
                array_map(static fn (CommitteeAssignmentStatus $status): string => $status->value, self::STATUS_ORDER),
            ),
        };

        $query->orderBy('committee_assignments.id', $direction);
    }
}
