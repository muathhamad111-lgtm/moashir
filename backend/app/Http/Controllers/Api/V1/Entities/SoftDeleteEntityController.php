<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\SoftDeleteEntity;
use App\Http\Requests\Entities\SoftDeleteEntityRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class SoftDeleteEntityController
{
    public function __invoke(SoftDeleteEntityRequest $request, Entity $entity, #[CurrentUser] User $actor, SoftDeleteEntity $softDelete): Response
    {
        $softDelete->handle($actor, $entity, $request->instruction());

        return ApiResponse::noContent();
    }
}
