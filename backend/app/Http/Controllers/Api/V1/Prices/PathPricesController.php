<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Prices;

use App\Actions\Prices\CancelScheduledPrice;
use App\Actions\Prices\ListPriceHistory;
use App\Actions\Prices\SetPathPrice;
use App\Http\Requests\Prices\CancelScheduledPriceRequest;
use App\Http\Requests\Prices\IndexPriceHistoryRequest;
use App\Http\Requests\Prices\UpdatePathPriceRequest;
use App\Http\Resources\Prices\PriceHistoryItemResource;
use App\Http\Resources\Prices\PriceRowResource;
use App\Http\Resources\Prices\PriceRowsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Prices\PriceRowReader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PathPricesController
{
    public function index(PriceRowReader $rows): JsonResponse
    {
        return ApiResponse::resource(new PriceRowsResource($rows->all()));
    }

    public function history(IndexPriceHistoryRequest $request, ListPriceHistory $history): JsonResponse
    {
        return ApiResponse::paginated($history->handle($request->purpose(), $request->perPage()), PriceHistoryItemResource::class);
    }

    public function update(UpdatePathPriceRequest $request, #[CurrentUser] User $actor, SetPathPrice $setPrice, PriceRowReader $rows): JsonResponse
    {
        $outcome = $setPrice->handle($actor, $request->purpose(), $request->toChange());

        return ApiResponse::resource(new PriceRowResource($rows->read($request->purpose())), meta: $outcome->toMeta());
    }

    public function cancelScheduled(CancelScheduledPriceRequest $request, #[CurrentUser] User $actor, CancelScheduledPrice $cancel, PriceRowReader $rows): JsonResponse
    {
        $cancel->handle($actor, $request->purpose(), $request->reason());

        return ApiResponse::resource(new PriceRowResource($rows->read($request->purpose())));
    }
}
