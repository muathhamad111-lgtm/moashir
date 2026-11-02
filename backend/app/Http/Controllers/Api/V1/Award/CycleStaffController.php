<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ShowAwardCycle;
use App\Actions\Award\SyncCycleInitialAssessors;
use App\Actions\Award\SyncCycleMembers;
use App\Http\Requests\Award\SyncCycleInitialAssessorsRequest;
use App\Http\Requests\Award\SyncCycleMembersRequest;
use App\Http\Resources\Award\AwardCycleDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CycleStaffController
{
    public function members(SyncCycleMembersRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, SyncCycleMembers $sync, ShowAwardCycle $show): JsonResponse
    {
        $updated = $sync->handle($actor, $cycle, $request->changes(), $request->reason());

        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $updated)));
    }

    public function initialAssessors(SyncCycleInitialAssessorsRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, SyncCycleInitialAssessors $sync, ShowAwardCycle $show): JsonResponse
    {
        $updated = $sync->handle($actor, $cycle, $request->assessorIds(), $request->reason());

        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $updated)));
    }
}
