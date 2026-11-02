<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ConfirmSuperAdminEmailChange;
use App\Http\Requests\Identity\ConfirmOwnEmailChangeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class ConfirmOwnEmailChangeController
{
    public function __invoke(ConfirmOwnEmailChangeRequest $request, #[CurrentUser] User $user, ConfirmSuperAdminEmailChange $confirmChange): Response
    {
        $confirmChange->handle($user, $request->challengeId(), $request->code());

        return ApiResponse::noContent();
    }
}
