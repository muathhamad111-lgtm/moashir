<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ResolveSessionContext;
use App\Http\Resources\Identity\SessionUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CurrentSessionController
{
    public function __invoke(#[CurrentUser] User $user, ResolveSessionContext $resolveContext): JsonResponse
    {
        return ApiResponse::resource(new SessionUserResource($resolveContext->handle($user)));
    }
}
