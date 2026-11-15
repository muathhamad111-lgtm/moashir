<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\CancelInvitation;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class CancelInvitationController
{
    public function __invoke(User $managedUser, #[CurrentUser] User $actor, CancelInvitation $cancelInvitation): Response
    {
        $cancelInvitation->handle($actor, $managedUser);

        return ApiResponse::noContent();
    }
}
