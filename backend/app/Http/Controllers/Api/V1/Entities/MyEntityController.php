<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\UpdateOwnEntityProfile;
use App\Http\Requests\Entities\UpdateOwnEntityRequest;
use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyEntityController
{
    public function show(#[CurrentUser] User $owner, OwnEntity $ownEntity): JsonResponse
    {
        return ApiResponse::resource(new OwnEntityResource($this->loaded($ownEntity->of($owner))));
    }

    public function update(UpdateOwnEntityRequest $request, #[CurrentUser] User $owner, UpdateOwnEntityProfile $updateProfile): JsonResponse
    {
        $entity = $updateProfile->handle($owner, $request->profile(), $request->contact(), $request->marketingConsent());

        return ApiResponse::resource(new OwnEntityResource($this->loaded($entity)));
    }

    private function loaded(Entity $entity): Entity
    {
        return $entity->load(OwnEntityResource::RELATIONS);
    }
}
