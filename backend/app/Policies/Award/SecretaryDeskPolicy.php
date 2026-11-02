<?php

declare(strict_types=1);

namespace App\Policies\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Identity\User;
use App\Services\Award\CycleRoleScope;
use Illuminate\Auth\Access\Response;

final readonly class SecretaryDeskPolicy
{
    public function __construct(private CycleRoleScope $scope) {}

    public function review(User $actor, AwardCycle $cycle, ?Assessment $assessment = null): Response
    {
        if ($assessment instanceof Assessment && ! $this->isCommitteeRequestOf($cycle, $assessment)) {
            return Response::denyAsNotFound();
        }

        return $this->scope->respond($this->reviews($actor, $cycle), $actor, $cycle, PermissionKey::AwardSecretaryReview);
    }

    public function decide(User $actor, AwardCycle $cycle, Assessment $assessment): Response
    {
        if (! $this->isCommitteeRequestOf($cycle, $assessment)) {
            return Response::denyAsNotFound();
        }

        $decides = $this->scope->allows($actor, $cycle, PermissionKey::AwardSecretaryDecide, [], [CycleMemberRole::AwardSecretary]);

        return $this->scope->respond($decides, $actor, $cycle, PermissionKey::AwardSecretaryDecide);
    }

    public function viewEvaluation(User $actor, AwardCycle $cycle, Assessment $assessment, CommitteeAssignment $assignment): Response
    {
        return $assignment->assessment_id === $assessment->id ? $this->review($actor, $cycle, $assessment) : Response::denyAsNotFound();
    }

    public function approveSubstitution(User $actor, AwardCycle $cycle, Assessment $assessment, CommitteeAssignment $substitution): Response
    {
        return $substitution->assessment_id === $assessment->id && $substitution->replaces_assignment_id !== null
            ? $this->decide($actor, $cycle, $assessment)
            : Response::denyAsNotFound();
    }

    public function closeDiscussion(User $actor, AwardCycle $cycle, Assessment $assessment, CommitteeDiscussion $discussion): Response
    {
        return $discussion->assessment_id === $assessment->id ? $this->decide($actor, $cycle, $assessment) : Response::denyAsNotFound();
    }

    private function reviews(User $actor, AwardCycle $cycle): bool
    {
        return $this->scope->allows($actor, $cycle, PermissionKey::AwardSecretaryReview, [RoleKey::SystemAdmin], [CycleMemberRole::AwardSecretary]);
    }

    private function isCommitteeRequestOf(AwardCycle $cycle, Assessment $assessment): bool
    {
        return $assessment->isAdvanced()
            && $assessment->award_cycle_id === $cycle->id
            && $assessment->status instanceof AdvancedAssessmentStatus
            && ($assessment->status->isInCommittee() || $assessment->status->isResult());
    }
}
