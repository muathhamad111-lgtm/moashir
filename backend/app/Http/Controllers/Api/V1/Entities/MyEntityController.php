<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyEntityController
{
    public function show(#[CurrentUser] User $owner, OwnEntity $ownEntity): JsonResponse
    {
        return ApiResponse::resource(new OwnEntityResource($ownEntity->of($owner)->load(OwnEntityResource::RELATIONS)));
    }
}
