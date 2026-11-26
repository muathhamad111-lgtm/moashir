<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ListEntityProfileChanges;
use App\Http\Requests\Entities\IndexProfileChangesRequest;
use App\Http\Resources\Entities\ProfileChangeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyProfileChangeController
{
    public function __invoke(
        IndexProfileChangesRequest $request,
        #[CurrentUser] User $owner,
        OwnEntity $ownEntity,
        ListEntityProfileChanges $listChanges,
    ): JsonResponse {
        return ApiResponse::paginated($listChanges->handle($ownEntity->of($owner), $request->perPage(), $request->section()), ProfileChangeResource::class);
    }
}
