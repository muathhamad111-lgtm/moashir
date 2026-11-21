<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\CountAssessments;
use App\Actions\Assessments\ListAssessmentRows;
use App\Actions\Assessments\ShowAssessmentRecord;
use App\Http\Requests\Assessments\IndexAssessmentsRequest;
use App\Http\Resources\Assessments\AssessmentRecordResource;
use App\Http\Resources\Assessments\AssessmentRowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessmentController
{
    public function index(IndexAssessmentsRequest $request, #[CurrentUser] User $actor, ListAssessmentRows $listRows, CountAssessments $countAssessments): JsonResponse
    {
        $filters = $request->filters();
        $page = $listRows->handle($actor, $filters);

        return ApiResponse::paginated($page->rows, AssessmentRowResource::class, [
            'counts' => $countAssessments->handle($actor, $filters)->toArray(),
            'missed_periods' => $page->missedTotal,
        ]);
    }

    public function show(#[CurrentUser] User $viewer, Assessment $assessment, ShowAssessmentRecord $showRecord): JsonResponse
    {
        return ApiResponse::resource(new AssessmentRecordResource($showRecord->handle($viewer, $assessment)));
    }
}
