<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ChangeOwnPassword;
use App\Http\Requests\Identity\ChangePasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class ChangePasswordController
{
    public function __invoke(ChangePasswordRequest $request, #[CurrentUser] User $user, ChangeOwnPassword $changePassword): Response
    {
        $changePassword->handle($user, $request->password());

        return ApiResponse::noContent();
    }
}
