<?php

declare(strict_types=1);

namespace App\Policies\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserSession;
use Illuminate\Auth\Access\Response;

final class UserSessionPolicy
{
    public function revoke(User $actor, UserSession $session): Response
    {
        return $session->user_id === $actor->id ? Response::allow() : Response::denyAsNotFound();
    }
}
