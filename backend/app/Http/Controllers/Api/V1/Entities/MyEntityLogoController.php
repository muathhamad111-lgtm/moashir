<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\RemoveOwnEntityLogo;
use App\Actions\Entities\UploadOwnEntityLogo;
use App\Http\Requests\Entities\UploadEntityLogoRequest;
use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyEntityLogoController
{
    public function store(UploadEntityLogoRequest $request, #[CurrentUser] User $owner, UploadOwnEntityLogo $uploadLogo): JsonResponse
    {
        $entity = $uploadLogo->handle($owner, $request->upload());

        return ApiResponse::resource(new OwnEntityResource($entity->load(OwnEntityResource::RELATIONS)), Response::HTTP_CREATED);
    }

    public function destroy(#[CurrentUser] User $owner, RemoveOwnEntityLogo $removeLogo): JsonResponse
    {
        $entity = $removeLogo->handle($owner);

        return ApiResponse::resource(new OwnEntityResource($entity->load(OwnEntityResource::RELATIONS)));
    }
}
