<?php

declare(strict_types=1);

namespace App\Policies\Assessments;

use App\Enums\Assessments\AssessmentAccess;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use App\Services\Award\CommitteeSeating;
use App\Services\Identity\RequestPathScope;
use Illuminate\Auth\Access\Response;

final readonly class AssessmentPolicy
{
    public function __construct(private AssessmentRecordScope $scope, private CommitteeSeating $committee, private RequestPathScope $paths) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionKey::AssessmentHistoryView->value);
    }

    public function view(User $actor, Assessment $assessment): Response
    {
        if (! $actor->can(PermissionKey::AssessmentHistoryView->value)) {
            return Response::deny();
        }

        return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewResult(User $actor, Assessment $assessment): Response
    {
        return $this->view($actor, $assessment);
    }

    public function createSelf(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::AssessmentSelfManage->value);
    }

    public function viewAccreditedCurrent(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::AssessmentAccreditedManage->value);
    }

    public function viewAdvancedCurrent(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::AssessmentAdvancedManage->value);
    }

    public function applyAdvanced(User $actor): bool
    {
        return $this->viewAdvancedCurrent($actor);
    }

    public function update(User $actor, Assessment $assessment): Response
    {
        return $this->manageOwn($actor, $assessment, AssessmentPath::SelfAssessment, PermissionKey::AssessmentSelfManage);
    }

    public function submit(User $actor, Assessment $assessment): Response
    {
        return $this->manageOwn($actor, $assessment, $assessment->path, $this->pathPermission($assessment->path));
    }

    public function saveCv(User $actor, Assessment $assessment): Response
    {
        return $this->manageOwnReviewed($actor, $assessment, $this->pathPermission($assessment->path));
    }

    public function manageEvidence(User $actor, Assessment $assessment): Response
    {
        return $this->manageOwnReviewed($actor, $assessment, PermissionKey::EvidenceManage);
    }

    public function deleteDraft(User $actor, Assessment $assessment): Response
    {
        return $this->manageOwn($actor, $assessment, AssessmentPath::Advanced, PermissionKey::AssessmentAdvancedManage);
    }

    public function returnForCompletion(User $actor, Assessment $assessment): Response
    {
        return $this->followAdvanced($actor, $assessment);
    }

    public function withdraw(User $actor, Assessment $assessment): Response
    {
        return $this->followAdvanced($actor, $assessment);
    }

    public function removeEvidence(User $actor, Assessment $assessment, EvidenceFile $evidence): Response
    {
        return $evidence->assessment_id === $assessment->id ? $this->manageEvidence($actor, $assessment) : Response::denyAsNotFound();
    }

    public function viewEvidence(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->path->isReviewed()) {
            return Response::denyAsNotFound();
        }

        if (! $actor->can(PermissionKey::EvidenceView->value)) {
            return Response::deny();
        }

        return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::allow() : Response::denyAsNotFound();
    }

    public function downloadEvidence(User $actor, Assessment $assessment, EvidenceFile $evidence): Response
    {
        if ($evidence->assessment_id !== $assessment->id) {
            return Response::denyAsNotFound();
        }

        if ($actor->can(PermissionKey::EvidenceView->value) && $this->committee->sitsOn($actor, $assessment)) {
            return Response::allow();
        }

        return $this->viewEvidence($actor, $assessment);
    }

    public function requestEvidenceBundle(User $actor, Assessment $assessment): Response
    {
        $allowed = $this->viewEvidence($actor, $assessment);

        if ($allowed->denied() || $actor->isSuperAdmin()) {
            return $allowed;
        }

        return $this->scope->accessOf($actor, $assessment) === AssessmentAccess::Owner ? Response::deny() : Response::allow();
    }

    public function viewEvidenceBundle(User $actor, Assessment $assessment, EvidenceBundle $bundle): Response
    {
        if ($bundle->assessment_id !== $assessment->id || (! $actor->isSuperAdmin() && ! $bundle->isRequestedBy($actor->id))) {
            return Response::denyAsNotFound();
        }

        return $this->requestEvidenceBundle($actor, $assessment);
    }

    public function viewRequestQueue(User $actor): bool
    {
        return $actor->can(PermissionKey::RequestsQueueView->value);
    }

    public function viewAssignedRequests(User $actor): bool
    {
        return $actor->can(PermissionKey::RequestsAssignedView->value);
    }

    public function viewRequest(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->path->isReviewed()) {
            return Response::denyAsNotFound();
        }

        if ($this->followsQueueOf($actor, $assessment)) {
            return Response::allow();
        }

        if (! $actor->can(PermissionKey::RequestsAssignedView->value)) {
            return $actor->can(PermissionKey::RequestsQueueView->value) ? Response::denyAsNotFound() : Response::deny();
        }

        return $this->scope->isReviewedBy($actor, $assessment) ? Response::allow() : Response::denyAsNotFound();
    }

    public function listAssessors(User $actor): bool
    {
        return $actor->can(PermissionKey::RequestsAssign->value);
    }

    public function assign(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->path->isReviewed() || ! $this->paths->covers($actor, $assessment->path)) {
            return Response::denyAsNotFound();
        }

        return $actor->can(PermissionKey::RequestsAssign->value) ? Response::allow() : Response::deny();
    }

    public function remind(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->isAccredited() || ! $this->paths->covers($actor, $assessment->path)) {
            return Response::denyAsNotFound();
        }

        return $actor->can(PermissionKey::RequestsAssign->value) ? Response::allow() : Response::deny();
    }

    public function reassign(User $actor, Assessment $assessment): Response
    {
        return $this->assign($actor, $assessment);
    }

    public function review(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->path->isReviewed()) {
            return Response::denyAsNotFound();
        }

        $access = $this->scope->accessOf($actor, $assessment);

        if ($access === AssessmentAccess::Assignee && $actor->can(PermissionKey::AssessmentScoreManage->value)) {
            return Response::allow();
        }

        if ($access === AssessmentAccess::ReadOnly && $actor->can(PermissionKey::RequestsReviewRead->value)) {
            return Response::allow();
        }

        return $access->canRead() ? Response::deny() : Response::denyAsNotFound();
    }

    public function score(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->path->isReviewed()) {
            return Response::denyAsNotFound();
        }

        if ($actor->can(PermissionKey::AssessmentScoreManage->value) && $this->isActiveAssignee($actor, $assessment)) {
            return Response::allow();
        }

        return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::deny() : Response::denyAsNotFound();
    }

    public function override(User $actor, Assessment $assessment): Response
    {
        return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::deny() : Response::denyAsNotFound();
    }

    private function manageOwnReviewed(User $actor, Assessment $assessment, PermissionKey $permission): Response
    {
        return $assessment->path->isReviewed()
            ? $this->manageOwn($actor, $assessment, $assessment->path, $permission)
            : $this->manageOwn($actor, $assessment, AssessmentPath::Accredited, $permission);
    }

    private function followAdvanced(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->isAdvanced()) {
            return Response::denyAsNotFound();
        }

        if ($actor->can(PermissionKey::RequestsCompletionReturn->value) && $this->paths->covers($actor, $assessment->path)) {
            return Response::allow();
        }

        return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::deny() : Response::denyAsNotFound();
    }

    private function pathPermission(AssessmentPath $path): PermissionKey
    {
        return match ($path) {
            AssessmentPath::SelfAssessment => PermissionKey::AssessmentSelfManage,
            AssessmentPath::Accredited => PermissionKey::AssessmentAccreditedManage,
            AssessmentPath::Advanced => PermissionKey::AssessmentAdvancedManage,
        };
    }

    private function manageOwn(User $actor, Assessment $assessment, AssessmentPath $path, PermissionKey $permission): Response
    {
        if (! $assessment->isOwnedBy($actor->entity_id)) {
            return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::deny() : Response::denyAsNotFound();
        }

        return $assessment->path === $path && $actor->can($permission->value) ? Response::allow() : Response::deny();
    }

    private function followsQueueOf(User $actor, Assessment $assessment): bool
    {
        return $actor->can(PermissionKey::RequestsQueueView->value) && $this->paths->covers($actor, $assessment->path);
    }

    private function isActiveAssignee(User $actor, Assessment $assessment): bool
    {
        $assignment = $assessment->loadMissing('activeAssignment')->activeAssignment;

        return $assignment instanceof AssessmentAssignment && $assignment->isHeldBy($actor->id);
    }
}
