<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\CheckCriteriaVersion;
use App\Actions\Criteria\SummarizeCycleBindings;
use App\Http\Resources\Criteria\ConsistencyReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CheckCriteriaVersionController
{
    public function __invoke(CriteriaVersion $version, CheckCriteriaVersion $checkVersion, SummarizeCycleBindings $cycles): JsonResponse
    {
        return ApiResponse::resource(new ConsistencyReportResource($checkVersion->handle($version)), Response::HTTP_OK, [
            'award_cycles' => $cycles->handle()->toArray(),
        ]);
    }
}
