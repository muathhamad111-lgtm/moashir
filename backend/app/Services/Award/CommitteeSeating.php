<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;

final class CommitteeSeating
{
    public function sitsOn(User $user, Assessment $assessment): bool
    {
        $status = $assessment->status;

        if (! $status instanceof AdvancedAssessmentStatus || ! $status->isInCommittee()) {
            return false;
        }

        return CommitteeAssignment::query()
            ->where('assessment_id', $assessment->id)
            ->live()
            ->whereIn('cycle_committee_assessor_id', CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $user->id)->active())
            ->exists();
    }
}
