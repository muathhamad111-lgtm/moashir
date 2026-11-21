<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Identity\User;
use App\Services\Award\MyCommitteeAssignmentsQuery;
use App\Support\ScalarValue;

/**
 * Counts the viewer's committee assignments per status for the list tabs, honouring the cycle
 * filter and never the status filter.
 */
final readonly class CountMyCommitteeAssignments
{
    public function __construct(private MyCommitteeAssignmentsQuery $assignments) {}

    /**
     * @return array<string, int>
     */
    public function handle(User $viewer, ?int $cycleId): array
    {
        $counts = $this->assignments->for($viewer, $cycleId)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $tabs = [CommitteeAssignmentStatus::NotStarted, CommitteeAssignmentStatus::InProgress, CommitteeAssignmentStatus::Completed];

        return array_combine(
            array_map(static fn (CommitteeAssignmentStatus $status): string => $status->value, $tabs),
            array_map(static fn (CommitteeAssignmentStatus $status): int => ScalarValue::int($counts[$status->value] ?? null), $tabs),
        );
    }
}
