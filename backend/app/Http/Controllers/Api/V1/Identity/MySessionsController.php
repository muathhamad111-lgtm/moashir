<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\FindOwnSession;
use App\Actions\Identity\ListOwnSessions;
use App\Actions\Identity\RevokeOwnSession;
use App\Http\Resources\Identity\SessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class MySessionsController
{
    public function index(#[CurrentUser] User $user, ListOwnSessions $listSessions): JsonResponse
    {
        return ApiResponse::data(SessionResource::collection($listSessions->handle($user))->resolve(request()));
    }

    public function destroy(string $session, #[CurrentUser] User $user, FindOwnSession $findSession, RevokeOwnSession $revokeSession): Response
    {
        $ownSession = $findSession->handle($user, $session);
        Gate::authorize('revoke', $ownSession);

        $revokeSession->handle($user, $ownSession);

        return ApiResponse::noContent();
    }
}
