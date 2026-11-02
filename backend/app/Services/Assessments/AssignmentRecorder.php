<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AssignmentEndReason;
use App\Enums\Assessments\AssignmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final class AssignmentRecorder
{
    public function assign(Assessment $locked, User $assessor, User $assignedBy, ?string $reason): AssessmentAssignment
    {
        $assignment = AssessmentAssignment::query()->create([
            'assessment_id' => $locked->id,
            'assessor_user_id' => $assessor->id,
            'assigned_by_user_id' => $assignedBy->id,
            'status' => AssignmentStatus::Active,
            'reason' => $reason,
        ]);
        $locked->setRelation('activeAssignment', $assignment);

        return $assignment;
    }

    public function handOver(Assessment $locked, AssessmentAssignment $current, User $assessor, User $assignedBy, string $reason): AssessmentAssignment
    {
        $this->close($current, AssignmentStatus::Reassigned, $reason);

        return $this->assign($locked, $assessor, $assignedBy, $reason);
    }

    public function endActive(Assessment $locked, AssignmentEndReason $reason): ?AssessmentAssignment
    {
        $active = $locked->loadMissing('activeAssignment')->activeAssignment;

        if (! $active instanceof AssessmentAssignment) {
            return null;
        }

        $this->close($active, AssignmentStatus::Ended, $reason->value);
        $locked->setRelation('activeAssignment', null);

        return $active;
    }

    public function complete(Assessment $locked, AssessmentAssignment $assignment): void
    {
        $this->close($assignment, AssignmentStatus::Completed, null);
        $locked->setRelation('activeAssignment', null);
    }

    public function markStarted(AssessmentAssignment $assignment): void
    {
        if ($assignment->started_at === null) {
            $assignment->forceFill(['started_at' => Date::now()])->save();
        }
    }

    private function close(AssessmentAssignment $assignment, AssignmentStatus $status, ?string $reason): void
    {
        $assignment->forceFill(['status' => $status, 'ended_at' => Date::now(), 'end_reason' => $reason])->save();
    }
}
