<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\AnnounceCycleResults;
use App\Actions\Award\CloseAwardCycle;
use App\Actions\Award\CloseCycleSubmissions;
use App\Actions\Award\OpenCycleSubmissions;
use App\Actions\Award\ReopenAwardCycle;
use App\Actions\Award\ShowAwardCycle;
use App\Actions\Award\StartCommitteeAssessment;
use App\Http\Requests\Assessments\OverrideReasonRequest;
use App\Http\Requests\Award\CycleActionRequest;
use App\Http\Resources\Award\AwardCycleDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CycleTransitionController
{
    public function open(CycleActionRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, OpenCycleSubmissions $open, ShowAwardCycle $show): JsonResponse
    {
        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $open->handle($actor, $cycle, $request->reason()))));
    }

    public function closeSubmissions(CycleActionRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, CloseCycleSubmissions $close, ShowAwardCycle $show): JsonResponse
    {
        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $close->handle($actor, $cycle, $request->reason()))));
    }

    public function startCommittee(CycleActionRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, StartCommitteeAssessment $start, ShowAwardCycle $show): JsonResponse
    {
        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $start->handle($actor, $cycle, $request->reason()))));
    }

    public function announce(CycleActionRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, AnnounceCycleResults $announce, ShowAwardCycle $show): JsonResponse
    {
        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $announce->handle($actor, $cycle, $request->reason()))));
    }

    public function close(CycleActionRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, CloseAwardCycle $close, ShowAwardCycle $show): JsonResponse
    {
        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $close->handle($actor, $cycle, $request->reason()))));
    }

    public function reopen(OverrideReasonRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, ReopenAwardCycle $reopen, ShowAwardCycle $show): JsonResponse
    {
        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $reopen->handle($actor, $cycle, $request->reason()))));
    }
}
