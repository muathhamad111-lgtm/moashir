<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\SaveSelfAssessmentDraft;
use App\Actions\Assessments\StartSelfAssessment;
use App\Http\Requests\Assessments\SaveSelfAssessmentDraftRequest;
use App\Http\Requests\Assessments\StartAssessmentRequest;
use App\Http\Resources\Assessments\AssessmentResource;
use App\Http\Resources\Assessments\AssessmentScoresResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SelfAssessmentController
{
    public function store(StartAssessmentRequest $request, #[CurrentUser] User $actor, StartSelfAssessment $startAssessment): JsonResponse
    {
        return ApiResponse::resource(new AssessmentResource($startAssessment->handle($actor)), Response::HTTP_CREATED);
    }

    public function update(SaveSelfAssessmentDraftRequest $request, Assessment $assessment, #[CurrentUser] User $actor, SaveSelfAssessmentDraft $saveDraft): JsonResponse
    {
        return ApiResponse::resource(new AssessmentScoresResource($saveDraft->handle($actor, $assessment, $request->scores(), $request->reason())));
    }
}
