<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\CreateAwardCycle;
use App\Actions\Award\ListAwardCycles;
use App\Actions\Award\ShowAwardCycle;
use App\Actions\Award\SummarizeAwardCycles;
use App\Actions\Award\UpdateAwardCycle;
use App\Http\Requests\Award\IndexAwardCyclesRequest;
use App\Http\Requests\Award\StoreAwardCycleRequest;
use App\Http\Requests\Award\UpdateAwardCycleRequest;
use App\Http\Resources\Award\AwardCycleDetailResource;
use App\Http\Resources\Award\AwardCycleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AwardCycleController
{
    public function index(IndexAwardCyclesRequest $request, #[CurrentUser] User $viewer, ListAwardCycles $list, SummarizeAwardCycles $summarize): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($list->handle($viewer, $filters), AwardCycleResource::class, ['stats' => $summarize->handle($viewer, $filters)]);
    }

    public function store(StoreAwardCycleRequest $request, #[CurrentUser] User $actor, CreateAwardCycle $create, ShowAwardCycle $show): JsonResponse
    {
        $cycle = $create->handle($actor, $request->cycleAttributes(), $request->reason());

        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $cycle)), Response::HTTP_CREATED);
    }

    public function show(AwardCycle $cycle, #[CurrentUser] User $viewer, ShowAwardCycle $show): JsonResponse
    {
        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($viewer, $cycle)));
    }

    public function update(UpdateAwardCycleRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, UpdateAwardCycle $update, ShowAwardCycle $show): JsonResponse
    {
        $updated = $update->handle($actor, $cycle, $request->cycleAttributes(), $request->reason());

        return ApiResponse::resource(new AwardCycleDetailResource($show->handle($actor, $updated)));
    }
}
