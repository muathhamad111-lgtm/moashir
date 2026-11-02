<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\SignOut;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class LogoutController
{
    public function __invoke(#[CurrentUser] User $user, SignOut $signOut): Response
    {
        $signOut->handle($user);

        return ApiResponse::noContent();
    }
}
