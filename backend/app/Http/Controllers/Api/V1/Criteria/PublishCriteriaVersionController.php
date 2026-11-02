<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\PublishCriteriaVersion;
use App\Http\Requests\Criteria\PublishCriteriaVersionRequest;
use App\Http\Resources\Criteria\CriteriaVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PublishCriteriaVersionController
{
    public function __invoke(PublishCriteriaVersionRequest $request, CriteriaVersion $version, #[CurrentUser] User $actor, PublishCriteriaVersion $publish): JsonResponse
    {
        $outcome = $publish->handle($actor, $version, $request->instruction());

        return ApiResponse::resource(new CriteriaVersionResource($outcome->version), Response::HTTP_OK, [
            'edit_event_id' => $outcome->editEvent?->id,
            'affected_open_count' => $outcome->editEvent->affected_open_count ?? 0,
            'change_count' => $outcome->changeCount,
            'effect' => $outcome->effect?->toArray(),
            'award_cycles' => [...($outcome->cycles?->toArray() ?? []), 'draft_cycles' => $request->instruction()->draftCycles->value, 'moved_cycle_ids' => $outcome->movedCycleIds],
        ]);
    }
}
