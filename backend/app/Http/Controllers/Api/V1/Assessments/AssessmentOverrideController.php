<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\ForceAssessmentTransition;
use App\Actions\Assessments\OverrideAssessmentScores;
use App\Actions\Assessments\ReopenAssessment;
use App\Http\Requests\Assessments\ForceTransitionRequest;
use App\Http\Requests\Assessments\OverrideReasonRequest;
use App\Http\Requests\Assessments\OverrideScoresRequest;
use App\Http\Resources\Assessments\AssessmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessmentOverrideController
{
    public function scores(OverrideScoresRequest $request, Assessment $assessment, #[CurrentUser] User $actor, OverrideAssessmentScores $override): JsonResponse
    {
        return ApiResponse::resource(new AssessmentResource($override->handle($actor, $assessment, $request->scores(), $request->reason())));
    }

    public function reopen(OverrideReasonRequest $request, Assessment $assessment, #[CurrentUser] User $actor, ReopenAssessment $reopen): JsonResponse
    {
        return ApiResponse::resource(new AssessmentResource($reopen->handle($actor, $assessment, $request->reason())));
    }

    public function force(ForceTransitionRequest $request, Assessment $assessment, #[CurrentUser] User $actor, ForceAssessmentTransition $force): JsonResponse
    {
        return ApiResponse::resource(new AssessmentResource($force->handle($actor, $assessment, $request->target(), $request->reason())));
    }
}
