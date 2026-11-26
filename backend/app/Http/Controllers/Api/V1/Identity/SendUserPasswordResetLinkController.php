<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\SendUserPasswordResetLink;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SendUserPasswordResetLinkController
{
    public function __invoke(User $managedUser, #[CurrentUser] User $actor, SendUserPasswordResetLink $sendResetLink): JsonResponse
    {
        $sendResetLink->handle($actor, $managedUser);

        return ApiResponse::data(null, Response::HTTP_ACCEPTED);
    }
}
