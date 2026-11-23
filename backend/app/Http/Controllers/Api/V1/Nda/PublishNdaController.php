<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\PublishNdaDraft;
use App\Http\Requests\Nda\PublishNdaRequest;
use App\Http\Resources\Nda\NdaVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PublishNdaController
{
    public function __invoke(PublishNdaRequest $request, #[CurrentUser] User $actor, PublishNdaDraft $publish): JsonResponse
    {
        return ApiResponse::resource(new NdaVersionResource($publish->handle($actor, $request->publication())));
    }
}
