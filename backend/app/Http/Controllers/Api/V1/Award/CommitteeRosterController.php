<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\AppointCommitteeAssessor;
use App\Actions\Award\ListCommitteeCandidates;
use App\Actions\Award\ListCommitteeMembers;
use App\Actions\Award\RemoveCommitteeAssessor;
use App\Http\Requests\Award\AppointCommitteeAssessorRequest;
use App\Http\Requests\Award\CycleActionRequest;
use App\Http\Requests\Award\SearchCommitteeCandidatesRequest;
use App\Http\Resources\Award\CommitteeMemberResource;
use App\Http\Resources\Award\CycleCandidateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\AwardCycle;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CommitteeRosterController
{
    public function index(AwardCycle $cycle, ListCommitteeMembers $members): JsonResponse
    {
        return ApiResponse::data(CommitteeMemberResource::collection($members->handle($cycle))->resolve(request()));
    }

    public function candidates(SearchCommitteeCandidatesRequest $request, AwardCycle $cycle, ListCommitteeCandidates $candidates): JsonResponse
    {
        return ApiResponse::data(CycleCandidateResource::collection($candidates->handle($cycle, $request->search()))->resolve($request));
    }

    public function store(AppointCommitteeAssessorRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, AppointCommitteeAssessor $appoint): JsonResponse
    {
        $member = $appoint->handle($actor, $cycle, $request->userId(), $request->reason());

        return ApiResponse::resource(new CommitteeMemberResource($member->load('assessor')), Response::HTTP_CREATED);
    }

    public function destroy(CycleActionRequest $request, AwardCycle $cycle, CycleCommitteeAssessor $member, #[CurrentUser] User $actor, RemoveCommitteeAssessor $remove): Response
    {
        $remove->handle($actor, $cycle, $member, $request->reason());

        return ApiResponse::noContent();
    }
}
