<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ListMyCommitteeAssignments;
use App\Actions\Award\ListMyCommitteeDiscussions;
use App\Actions\Award\RecordDiscussionEntry;
use App\Actions\Award\ShowCommitteeEvaluation;
use App\Http\Requests\Award\IndexMyCommitteeAssignmentsRequest;
use App\Http\Requests\Award\IndexMyCommitteeDiscussionsRequest;
use App\Http\Requests\Award\RecordDiscussionEntryRequest;
use App\Http\Resources\Award\CommitteeAssignmentRowResource;
use App\Http\Resources\Award\CommitteeEvaluationResource;
use App\Http\Resources\Award\MyCommitteeDiscussionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyCommitteeWorkController
{
    public function assignments(IndexMyCommitteeAssignmentsRequest $request, #[CurrentUser] User $viewer, ListMyCommitteeAssignments $list): JsonResponse
    {
        $page = $list->handle($viewer, $request->filters());
        $items = [];

        foreach ($page->paginator->items() as $assignment) {
            if ($assignment instanceof CommitteeAssignment) {
                $items[] = new CommitteeAssignmentRowResource($assignment, $page->visibility)->resolve($request);
            }
        }

        return ApiResponse::page($page->paginator, $items);
    }

    public function show(CommitteeAssignment $assignment, #[CurrentUser] User $viewer, ShowCommitteeEvaluation $show): JsonResponse
    {
        return ApiResponse::resource(new CommitteeEvaluationResource($show->handle($viewer, $assignment)));
    }

    public function discussions(IndexMyCommitteeDiscussionsRequest $request, #[CurrentUser] User $viewer, ListMyCommitteeDiscussions $list): JsonResponse
    {
        $page = $list->handle($viewer, $request->status(), $request->perPage());
        $items = [];

        foreach ($page->paginator->items() as $discussion) {
            if ($discussion instanceof CommitteeDiscussion) {
                $items[] = new MyCommitteeDiscussionResource($discussion, $page->visibility, $page->memberIds)->resolve($request);
            }
        }

        return ApiResponse::page($page->paginator, $items);
    }

    public function respond(RecordDiscussionEntryRequest $request, CommitteeDiscussion $discussion, #[CurrentUser] User $actor, RecordDiscussionEntry $record): JsonResponse
    {
        $entry = $record->handle($actor, $discussion, $request->kind(), $request->body(), $request->reason());

        return ApiResponse::data([
            'id' => $entry->id,
            'discussion_id' => $discussion->id,
            'kind' => $entry->kind->value,
            'body' => $entry->body,
            'at' => $entry->created_at->toIso8601String(),
        ], Response::HTTP_CREATED);
    }
}
