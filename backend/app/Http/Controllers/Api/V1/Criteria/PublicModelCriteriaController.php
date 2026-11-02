<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ListPublicModelCriteria;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PublicModelCriteriaController
{
    public function __invoke(ListPublicModelCriteria $listModel): JsonResponse
    {
        return ApiResponse::data($listModel->handle());
    }
}
