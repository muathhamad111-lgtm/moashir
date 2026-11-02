<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\CloseCommitteeDiscussion;
use App\Actions\Award\OpenCommitteeDiscussion;
use App\Http\Requests\Award\CloseCommitteeDiscussionRequest;
use App\Http\Requests\Award\OpenCommitteeDiscussionRequest;
use App\Http\Resources\Award\CommitteeDiscussionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SecretaryDiscussionController
{
    private const array RELATIONS = ['currentEntries.member.assessor', 'openedBy'];

    public function store(OpenCommitteeDiscussionRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, OpenCommitteeDiscussion $open): JsonResponse
    {
        $discussion = $open->handle($actor, $cycle, $assessment, $request->instruction());

        return ApiResponse::resource(new CommitteeDiscussionResource($discussion->load(self::RELATIONS)), Response::HTTP_CREATED);
    }

    public function close(CloseCommitteeDiscussionRequest $request, AwardCycle $cycle, Assessment $assessment, CommitteeDiscussion $discussion, #[CurrentUser] User $actor, CloseCommitteeDiscussion $close): JsonResponse
    {
        $closed = $close->handle($actor, $cycle, $assessment, $discussion, $request->summary(), $request->reason());

        return ApiResponse::resource(new CommitteeDiscussionResource($closed->load(self::RELATIONS)));
    }
}
