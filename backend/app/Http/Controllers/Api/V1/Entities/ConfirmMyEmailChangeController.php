<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ConfirmEntityEmailChange;
use App\Http\Requests\Identity\ConfirmOwnEmailChangeRequest;
use App\Http\Resources\Entities\OwnEmailChangeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ConfirmMyEmailChangeController
{
    public function __invoke(ConfirmOwnEmailChangeRequest $request, #[CurrentUser] User $owner, ConfirmEntityEmailChange $confirmChange): JsonResponse
    {
        return ApiResponse::resource(new OwnEmailChangeResource($confirmChange->handle($owner, $request->challengeId(), $request->code())));
    }
}
