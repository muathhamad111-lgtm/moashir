<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\PassSubmissionStation;
use App\Http\Requests\Assessments\PassSubmissionStationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SubmissionStationController
{
    public function __invoke(PassSubmissionStationRequest $request, Assessment $assessment, int $mainCriterion, #[CurrentUser] User $actor, PassSubmissionStation $pass): JsonResponse
    {
        return ApiResponse::data(['passed_stations' => $pass->handle($actor, $assessment, $mainCriterion, $request->reason())]);
    }
}
