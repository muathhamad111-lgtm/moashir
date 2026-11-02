<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListAbandonedCarts;
use App\Http\Requests\Finance\IndexAbandonedCartsRequest;
use App\Http\Resources\Finance\AbandonedCartResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AbandonedCartController
{
    public function __invoke(IndexAbandonedCartsRequest $request, ListAbandonedCarts $listCarts): JsonResponse
    {
        return ApiResponse::paginated($listCarts->handle($request->filters()), AbandonedCartResource::class);
    }
}
