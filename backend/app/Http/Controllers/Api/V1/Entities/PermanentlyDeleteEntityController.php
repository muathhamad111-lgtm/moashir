<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\PermanentlyDeleteEntity;
use App\Http\Requests\Entities\PermanentlyDeleteEntityRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use Illuminate\Http\JsonResponse;

final class PermanentlyDeleteEntityController
{
    public function __invoke(PermanentlyDeleteEntityRequest $request, Entity $entity, PermanentlyDeleteEntity $permanentlyDelete): JsonResponse
    {
        return ApiResponse::data($permanentlyDelete->handle($entity, $request->instruction())->toArray());
    }
}
