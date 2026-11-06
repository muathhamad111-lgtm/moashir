<?php

declare(strict_types=1);

namespace App\Policies\Reports;

use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRecordScope;
use Illuminate\Auth\Access\Response;

final readonly class FeedbackReportPolicy
{
    public function __construct(private AssessmentRecordScope $scope) {}

    public function view(User $actor, Assessment $assessment): Response
    {
        if (! $assessment->isAccredited()) {
            return Response::denyAsNotFound();
        }

        if (! $actor->can(PermissionKey::ReportsFeedbackView->value)) {
            return Response::deny();
        }

        return $this->scope->accessOf($actor, $assessment)->canRead() ? Response::allow() : Response::denyAsNotFound();
    }
}
