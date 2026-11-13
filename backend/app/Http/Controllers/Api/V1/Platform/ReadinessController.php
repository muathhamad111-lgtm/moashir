<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\CheckReadiness;
use App\Data\Platform\ReadinessViewer;
use App\Http\Resources\Platform\ReadinessResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReadinessController
{
    public function __invoke(Request $request, CheckReadiness $check): JsonResponse
    {
        $viewer = $request->user();
        $report = $check->handle(new ReadinessViewer($request->ip(), $viewer instanceof User ? $viewer : null));

        return ApiResponse::resource(new ReadinessResource($report), $report->status()->httpStatus());
    }
}
