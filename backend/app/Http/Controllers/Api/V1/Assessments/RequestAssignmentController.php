<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\AssignAssessor;
use App\Actions\Assessments\ListEligibleAssessors;
use App\Actions\Assessments\ReassignAssessor;
use App\Actions\Assessments\ShowRequest;
use App\Http\Requests\Assessments\AssignAssessorRequest;
use App\Http\Requests\Assessments\ListEligibleAssessorsRequest;
use App\Http\Requests\Assessments\ReassignAssessorRequest;
use App\Http\Resources\Assessments\EligibleAssessorResource;
use App\Http\Resources\Assessments\RequestDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssignmentReasonPolicy;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RequestAssignmentController
{
    public function assessors(ListEligibleAssessorsRequest $request, #[CurrentUser] User $actor, ListEligibleAssessors $list, AssignmentReasonPolicy $reasons): JsonResponse
    {
        $assessors = EligibleAssessorResource::collection($list->handle($actor, $request->assessmentPath(), $request->assessment(), $request->search()))->resolve($request);

        return ApiResponse::data($assessors, meta: ['reason_rules' => $reasons->rules()->toArray()]);
    }

    public function assign(AssignAssessorRequest $request, Assessment $assessment, #[CurrentUser] User $actor, AssignAssessor $assign, ShowRequest $show): JsonResponse
    {
        $assigned = $assign->handle($actor, $assessment, $request->assessorId(), $request->reason());

        return ApiResponse::resource(new RequestDetailResource($show->handle($actor, $assigned)));
    }

    public function reassign(ReassignAssessorRequest $request, Assessment $assessment, #[CurrentUser] User $actor, ReassignAssessor $reassign, ShowRequest $show): JsonResponse
    {
        $reassigned = $reassign->handle($actor, $assessment, $request->assessorId(), $request->reason());

        return ApiResponse::resource(new RequestDetailResource($show->handle($actor, $reassigned)));
    }
}
