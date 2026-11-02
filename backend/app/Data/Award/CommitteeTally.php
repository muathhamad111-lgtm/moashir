<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\CommitteeAssignment;

final readonly class CommitteeTally
{
    /**
     * @param  list<CommitteeAssignment>  $counted
     * @param  list<CommitteeAssignment>  $pendingSubstitutes
     */
    public function __construct(public array $counted, public array $pendingSubstitutes) {}

    public function isComplete(): bool
    {
        return $this->counted !== [] && $this->pendingIds() === [];
    }

    /**
     * @return list<int>
     */
    public function pendingIds(): array
    {
        return array_values(array_map(
            static fn (CommitteeAssignment $assignment): int => $assignment->id,
            array_filter($this->counted, static fn (CommitteeAssignment $assignment): bool => ! $assignment->isCompleted()),
        ));
    }

    public function completedCount(): int
    {
        return count($this->counted) - count($this->pendingIds());
    }

    /**
     * @return list<int>
     */
    public function pendingSubstituteIds(): array
    {
        return array_values(array_map(static fn (CommitteeAssignment $assignment): int => $assignment->id, $this->pendingSubstitutes));
    }
}
