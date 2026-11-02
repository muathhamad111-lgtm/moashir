<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListServicePrices;
use App\Http\Resources\Finance\ServicePricesResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ServicePricesController
{
    public function __invoke(ListServicePrices $listPrices): JsonResponse
    {
        return ApiResponse::resource(new ServicePricesResource($listPrices->handle()));
    }
}
