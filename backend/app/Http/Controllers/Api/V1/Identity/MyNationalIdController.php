<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ProvideOwnNationalId;
use App\Http\Requests\Identity\ProvideNationalIdRequest;
use App\Http\Resources\Identity\OwnNationalIdResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyNationalIdController
{
    public function store(ProvideNationalIdRequest $request, #[CurrentUser] User $user, ProvideOwnNationalId $provide): JsonResponse
    {
        return ApiResponse::resource(new OwnNationalIdResource($provide->handle($user, $request->nationalId())), Response::HTTP_CREATED);
    }
}
