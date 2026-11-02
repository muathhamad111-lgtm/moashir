<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\GetAssessmentResult;
use App\Http\Resources\Assessments\AssessmentResultResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use Illuminate\Http\JsonResponse;

final class AssessmentResultController
{
    public function __invoke(Assessment $assessment, GetAssessmentResult $getResult): JsonResponse
    {
        return ApiResponse::resource(new AssessmentResultResource($getResult->handle($assessment)));
    }
}
