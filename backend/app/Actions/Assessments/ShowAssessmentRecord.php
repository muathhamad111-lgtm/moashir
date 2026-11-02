<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentRecord;
use App\Data\Assessments\StatusHistoryEntry;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use App\Services\Identity\StaffActorReader;

final readonly class ShowAssessmentRecord
{
    public function __construct(private AssessmentRecordScope $scope, private StaffActorReader $staffActors) {}

    public function handle(User $viewer, Assessment $assessment): AssessmentRecord
    {
        $history = $this->statusHistoryOf($assessment);

        if (! $this->scope->accessOf($viewer, $assessment)->revealsStaffIdentities()) {
            return AssessmentRecord::forOwner($assessment, $history);
        }

        return AssessmentRecord::forStaff($assessment, $history, $this->staffActors->for($this->staffUserIdsOf($history)));
    }

    /**
     * @return list<StatusHistoryEntry>
     */
    private function statusHistoryOf(Assessment $assessment): array
    {
        $ownerId = $this->ownerIdOf($assessment);

        return array_values(AssessmentStatusTransition::query()
            ->where('assessment_id', $assessment->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (AssessmentStatusTransition $transition): StatusHistoryEntry => StatusHistoryEntry::from($transition, $ownerId))
            ->all());
    }

    private function ownerIdOf(Assessment $assessment): ?int
    {
        $ownerId = User::query()->where('entity_id', $assessment->entity_id)->value('id');

        return is_numeric($ownerId) ? (int) $ownerId : null;
    }

    /**
     * @param  list<StatusHistoryEntry>  $history
     * @return list<int>
     */
    private function staffUserIdsOf(array $history): array
    {
        return array_values(array_filter(array_map(static fn (StatusHistoryEntry $entry): ?int => $entry->staffUserId, $history)));
    }
}
