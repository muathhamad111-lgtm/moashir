<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ExportEntityPersonalData;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use Illuminate\Http\JsonResponse;

final class ExportEntityPersonalDataController
{
    public function __invoke(RequiredReasonRequest $request, Entity $entity, ExportEntityPersonalData $exportPersonalData): JsonResponse
    {
        return ApiResponse::data($exportPersonalData->handle($entity, $request->reason()));
    }
}
