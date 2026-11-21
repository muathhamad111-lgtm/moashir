<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\CancelOwnEntityEmailChange;
use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CancelMyEmailChangeController
{
    public function __invoke(#[CurrentUser] User $owner, CancelOwnEntityEmailChange $cancel, OwnEntity $ownEntity): JsonResponse
    {
        $changed = $cancel->handle($owner);

        return ApiResponse::resource(
            new OwnEntityResource($ownEntity->of($owner)->load(OwnEntityResource::RELATIONS)),
            Response::HTTP_OK,
            ['changed' => $changed],
        );
    }
}
