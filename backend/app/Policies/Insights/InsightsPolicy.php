<?php

declare(strict_types=1);

namespace App\Policies\Insights;

use App\Enums\Assessments\AssessmentAccess;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use Illuminate\Auth\Access\Response;

final readonly class InsightsPolicy
{
    public function __construct(private AssessmentRecordScope $scope) {}

    public function viewOwn(User $actor): bool
    {
        return $actor->entity_id !== null && $actor->can(PermissionKey::ReportsInsightsOwnView->value);
    }

    public function viewEntity(User $actor, Entity $entity): Response
    {
        if (! $actor->can(PermissionKey::ReportsInsightsView->value)) {
            return Response::deny();
        }

        if ($entity->trashed() && ! $actor->can(PermissionKey::EntitiesDelete->value)) {
            return Response::denyAsNotFound();
        }

        return $this->scope->readsEveryAssessment($actor) ? Response::allow() : Response::deny();
    }

    public function view(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->path->isReviewed()) {
            return Response::denyAsNotFound();
        }

        $ownView = $actor->can(PermissionKey::ReportsInsightsOwnView->value);
        $staffView = $actor->can(PermissionKey::ReportsInsightsView->value);

        if (! $ownView && ! $staffView) {
            return Response::deny();
        }

        $access = $this->scope->accessOf($actor, $assessment);

        return ($ownView && $access === AssessmentAccess::Owner) || ($staffView && $access === AssessmentAccess::ReadOnly)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
