<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\CountAssessments;
use App\Actions\Assessments\ListAssessments;
use App\Actions\Assessments\ShowAssessmentRecord;
use App\Http\Requests\Assessments\IndexAssessmentsRequest;
use App\Http\Resources\Assessments\AssessmentRecordResource;
use App\Http\Resources\Assessments\AssessmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessmentController
{
    public function index(IndexAssessmentsRequest $request, #[CurrentUser] User $actor, ListAssessments $listAssessments, CountAssessments $countAssessments): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($listAssessments->handle($actor, $filters), AssessmentResource::class, [
            'counts' => $countAssessments->handle($actor, $filters)->toArray(),
        ]);
    }

    public function show(#[CurrentUser] User $viewer, Assessment $assessment, ShowAssessmentRecord $showRecord): JsonResponse
    {
        return ApiResponse::resource(new AssessmentRecordResource($showRecord->handle($viewer, $assessment)));
    }
}
