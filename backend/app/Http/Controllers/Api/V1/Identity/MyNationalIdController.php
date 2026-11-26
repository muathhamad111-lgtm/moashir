<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ProvideOwnNationalId;
use App\Enums\Identity\NationalIdState;
use App\Http\Requests\Identity\ProvideNationalIdRequest;
use App\Http\Resources\Identity\OwnNationalIdResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Identity\AccountCompletionResolver;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyNationalIdController
{
    /**
     * 201 when the number is saved (Super Admin), 202 when it waits for an identity reviewer.
     */
    public function store(ProvideNationalIdRequest $request, #[CurrentUser] User $user, ProvideOwnNationalId $provide, AccountCompletionResolver $completion): JsonResponse
    {
        $state = $provide->handle($user, $request->nationalId());

        return ApiResponse::resource(
            new OwnNationalIdResource($completion->of($user)->nationalId),
            $state === NationalIdState::Provided ? Response::HTTP_CREATED : Response::HTTP_ACCEPTED,
        );
    }
}
