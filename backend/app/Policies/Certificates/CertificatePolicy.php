<?php

declare(strict_types=1);

namespace App\Policies\Certificates;

use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use Illuminate\Auth\Access\Response;

final readonly class CertificatePolicy
{
    public function __construct(private AssessmentRecordScope $scope) {}

    public function viewAny(User $actor): bool
    {
        return $this->viewsOwn($actor) || $actor->can(PermissionKey::CertificatesManage->value);
    }

    public function view(User $actor, Certificate $certificate): Response
    {
        if ($this->viewsOwn($actor) && $certificate->isOwnedBy($actor->entity_id)) {
            return Response::allow();
        }

        return $this->manages($actor, $certificate) ? Response::allow() : Response::denyAsNotFound();
    }

    public function download(User $actor, Certificate $certificate): Response
    {
        return $this->view($actor, $certificate);
    }

    public function revoke(User $actor, Certificate $certificate): Response
    {
        if ($this->manages($actor, $certificate)) {
            return Response::allow();
        }

        return $this->view($actor, $certificate)->allowed() ? Response::deny() : Response::denyAsNotFound();
    }

    public function reissue(User $actor, Certificate $certificate): Response
    {
        return $this->revoke($actor, $certificate);
    }

    public function issueAccredited(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->isAccredited()) {
            return Response::denyAsNotFound();
        }

        if ($actor->can(PermissionKey::CertificatesIssueAccredited->value) && $this->scope->isReviewedBy($actor, $assessment)) {
            return Response::allow();
        }

        return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::deny() : Response::denyAsNotFound();
    }

    private function viewsOwn(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::CertificatesOwnView->value);
    }

    private function manages(User $actor, Certificate $certificate): bool
    {
        return $actor->can(PermissionKey::CertificatesManage->value)
            && $this->scope->accessOf($actor, $certificate->assessment)->canRead();
    }
}
