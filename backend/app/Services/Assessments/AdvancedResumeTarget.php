<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssignmentEndReason;
use App\Enums\Identity\UserStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;

/**
 * Where an advanced request goes when the entity resubmits a completion or a «continue» completion expires: back to
 * its assessor only while that assessor can still work on it (active account, not deleted). Otherwise it goes to the
 * assignment queue, and the stale assignment ends so that the request can be assigned again (PM review 2026-11-23).
 */
final readonly class AdvancedResumeTarget
{
    public function __construct(private AssignmentRecorder $assignments) {}

    public function resolve(Assessment $locked, ?AdvancedAssessmentStatus $resumesTo): AdvancedAssessmentStatus
    {
        if (! $resumesTo instanceof AdvancedAssessmentStatus) {
            return AdvancedAssessmentStatus::AwaitingReviewAssignment;
        }

        if ($this->assessorCanWork($locked)) {
            return $resumesTo;
        }

        $this->assignments->endActive($locked, AssignmentEndReason::AssessorUnavailable);

        return AdvancedAssessmentStatus::AwaitingReviewAssignment;
    }

    private function assessorCanWork(Assessment $locked): bool
    {
        $assignment = $locked->loadMissing('activeAssignment.assessor')->activeAssignment;
        $assessor = $assignment instanceof AssessmentAssignment ? $assignment->assessor : null;

        return $assessor instanceof User && ! $assessor->trashed() && $assessor->status === UserStatus::Active;
    }
}
