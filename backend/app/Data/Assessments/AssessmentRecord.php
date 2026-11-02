<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Identity\StaffActor;
use App\Models\Assessments\Assessment;

final readonly class AssessmentRecord
{
    /**
     * @param  list<StatusHistoryEntry>  $statusHistory
     * @param  array<int, StaffActor>|null  $staffActors
     */
    private function __construct(public Assessment $assessment, public array $statusHistory, private ?array $staffActors) {}

    /**
     * @param  list<StatusHistoryEntry>  $statusHistory
     */
    public static function forOwner(Assessment $assessment, array $statusHistory): self
    {
        return new self($assessment, $statusHistory, null);
    }

    /**
     * @param  list<StatusHistoryEntry>  $statusHistory
     * @param  array<int, StaffActor>  $staffActors
     */
    public static function forStaff(Assessment $assessment, array $statusHistory, array $staffActors): self
    {
        return new self($assessment, $statusHistory, $staffActors);
    }

    public function revealsStaffActors(): bool
    {
        return $this->staffActors !== null;
    }

    public function staffActorOf(StatusHistoryEntry $entry): ?StaffActor
    {
        if ($this->staffActors === null || $entry->staffUserId === null) {
            return null;
        }

        return $this->staffActors[$entry->staffUserId] ?? StaffActor::unknown();
    }
}
