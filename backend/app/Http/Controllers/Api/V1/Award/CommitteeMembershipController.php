<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\DisableCommitteeAssessor;
use App\Actions\Award\ExcludeCommitteeAssessor;
use App\Actions\Award\SubstituteCommitteeAssessor;
use App\Http\Requests\Award\CycleActionRequest;
use App\Http\Requests\Award\DisableCommitteeAssessorRequest;
use App\Http\Requests\Award\SubstituteCommitteeAssessorRequest;
use App\Http\Resources\Award\CommitteeMemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\AwardCycle;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CommitteeMembershipController
{
    public function disable(DisableCommitteeAssessorRequest $request, AwardCycle $cycle, CycleCommitteeAssessor $member, #[CurrentUser] User $actor, DisableCommitteeAssessor $disable): JsonResponse
    {
        $disabled = $disable->handle($actor, $cycle, $member, $request->reason(), $request->reason());

        return ApiResponse::resource(new CommitteeMemberResource($disabled->load('assessor')));
    }

    public function exclude(CycleActionRequest $request, AwardCycle $cycle, CycleCommitteeAssessor $member, #[CurrentUser] User $actor, ExcludeCommitteeAssessor $exclude): JsonResponse
    {
        $outcome = $exclude->handle($actor, $cycle, $member, $request->reason());

        return ApiResponse::resource(new CommitteeMemberResource($outcome->member->load('assessor')), Response::HTTP_OK, [
            'affected_request_ids' => $outcome->affectedRequestIds,
            'recomputed_request_ids' => $outcome->recomputedRequestIds,
        ]);
    }

    public function substitute(SubstituteCommitteeAssessorRequest $request, AwardCycle $cycle, CycleCommitteeAssessor $member, #[CurrentUser] User $actor, SubstituteCommitteeAssessor $substitute): JsonResponse
    {
        $seat = $substitute->handle($actor, $cycle, $member, $request->substituteUserId(), $request->reason());

        return ApiResponse::resource(new CommitteeMemberResource($seat->load('assessor')), Response::HTTP_CREATED);
    }
}
