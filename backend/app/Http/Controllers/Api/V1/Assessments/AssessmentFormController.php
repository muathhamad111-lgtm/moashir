<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\GetAssessmentForm;
use App\Actions\Assessments\GetSubmissionForm;
use App\Http\Resources\Assessments\AssessmentFormResource;
use App\Http\Resources\Assessments\SubmissionFormResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessmentFormController
{
    public function __invoke(Assessment $assessment, #[CurrentUser] User $viewer, GetAssessmentForm $getForm, GetSubmissionForm $getSubmissionForm): JsonResponse
    {
        return $assessment->path->isReviewed()
            ? ApiResponse::resource(new SubmissionFormResource($getSubmissionForm->handle($viewer, $assessment)))
            : ApiResponse::resource(new AssessmentFormResource($getForm->handle($assessment)));
    }
}
