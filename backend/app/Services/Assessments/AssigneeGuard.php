<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;

final class AssigneeGuard
{
    public function assertActsOn(User $actor, Assessment $locked): AssessmentAssignment
    {
        $assignment = $locked->loadMissing('activeAssignment')->activeAssignment;

        if (! $assignment instanceof AssessmentAssignment) {
            throw AssessmentRejected::assignmentRequired();
        }

        if (! $actor->isSuperAdmin() && ! $assignment->isHeldBy($actor->id)) {
            throw AssessmentRejected::notAssignee();
        }

        return $assignment;
    }
}
