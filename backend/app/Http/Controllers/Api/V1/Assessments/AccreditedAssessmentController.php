<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\GetAccreditedCurrent;
use App\Actions\Assessments\SaveSubmissionCv;
use App\Http\Requests\Assessments\SaveSubmissionCvRequest;
use App\Http\Resources\Assessments\AccreditedCurrentResource;
use App\Http\Resources\Assessments\CvSheetResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AccreditedAssessmentController
{
    public function current(#[CurrentUser] User $actor, GetAccreditedCurrent $current): JsonResponse
    {
        return ApiResponse::resource(new AccreditedCurrentResource($current->handle($actor)));
    }

    public function saveCv(SaveSubmissionCvRequest $request, Assessment $assessment, #[CurrentUser] User $actor, SaveSubmissionCv $saveCv): JsonResponse
    {
        return ApiResponse::resource(new CvSheetResource($saveCv->handle($actor, $assessment, $request->bodies(), $request->reason())));
    }
}
