<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\DisableMfa;
use App\Http\Requests\Identity\CurrentPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class DisableMfaController
{
    public function __invoke(CurrentPasswordRequest $request, #[CurrentUser] User $user, DisableMfa $disableMfa): Response
    {
        $disableMfa->handle($user);

        return ApiResponse::noContent();
    }
}
