<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\SaveCommitteeNotes;
use App\Actions\Award\SaveCommitteeScores;
use App\Actions\Award\SubmitCommitteeEvaluation;
use App\Http\Requests\Award\CycleActionRequest;
use App\Http\Requests\Award\SaveCommitteeNotesRequest;
use App\Http\Requests\Award\SaveCommitteeScoresRequest;
use App\Http\Resources\Award\CommitteeScoresResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyCommitteeEvaluationController
{
    public function scores(SaveCommitteeScoresRequest $request, CommitteeAssignment $assignment, #[CurrentUser] User $actor, SaveCommitteeScores $save): JsonResponse
    {
        return ApiResponse::resource(new CommitteeScoresResource($save->handle($actor, $assignment, $request->scores(), $request->reason())));
    }

    public function notes(SaveCommitteeNotesRequest $request, CommitteeAssignment $assignment, #[CurrentUser] User $actor, SaveCommitteeNotes $save): JsonResponse
    {
        return ApiResponse::data(['assignment_id' => $assignment->id, ...$save->handle($actor, $assignment, $request->changes(), $request->reason())->toArray()]);
    }

    public function submit(CycleActionRequest $request, CommitteeAssignment $assignment, #[CurrentUser] User $actor, SubmitCommitteeEvaluation $submit): JsonResponse
    {
        $submitted = $submit->handle($actor, $assignment, $request->reason());

        return ApiResponse::data([
            'id' => $submitted->id,
            'status' => $submitted->status->value,
            'percent' => $submitted->percent?->toDisplay(),
            'submitted_at' => $submitted->submitted_at?->toIso8601String(),
        ]);
    }
}
