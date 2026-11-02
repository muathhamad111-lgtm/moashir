<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\GetPublicCurrentCycle;
use App\Http\Resources\Award\PublicCycleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Award\AwardCycle;
use Illuminate\Http\JsonResponse;

final class PublicCycleController
{
    public function __invoke(GetPublicCurrentCycle $current): JsonResponse
    {
        $cycle = $current->handle();

        return $cycle instanceof AwardCycle ? ApiResponse::resource(new PublicCycleResource($cycle)) : ApiResponse::data(null);
    }
}
