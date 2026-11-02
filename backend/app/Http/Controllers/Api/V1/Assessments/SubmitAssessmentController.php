<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\GetAssessmentResult;
use App\Actions\Assessments\SubmitAccreditedRequest;
use App\Actions\Assessments\SubmitAdvancedRequest;
use App\Actions\Assessments\SubmitSelfAssessment;
use App\Http\Requests\Assessments\SubmitAssessmentRequest;
use App\Http\Resources\Assessments\AssessmentResource;
use App\Http\Resources\Assessments\AssessmentResultResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SubmitAssessmentController
{
    public function __invoke(
        SubmitAssessmentRequest $request,
        Assessment $assessment,
        #[CurrentUser] User $actor,
        SubmitSelfAssessment $submitSelf,
        SubmitAccreditedRequest $submitAccredited,
        SubmitAdvancedRequest $submitAdvanced,
        GetAssessmentResult $getResult,
    ): JsonResponse {
        if ($assessment->isAccredited()) {
            return ApiResponse::resource(new AssessmentResource($submitAccredited->handle($actor, $assessment, $request->reason())));
        }

        if ($assessment->isAdvanced()) {
            return ApiResponse::resource(new AssessmentResource($submitAdvanced->handle($actor, $assessment, $request->reason())));
        }

        $submitted = $submitSelf->handle($actor, $assessment, $request->scores(), $request->reason());

        return ApiResponse::resource(new AssessmentResultResource($getResult->handle($submitted)));
    }
}
