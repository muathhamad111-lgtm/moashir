<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\UsablePasswordResetLinkFinder;
use App\Services\Identity\UserSessionTerminator;
use Illuminate\Support\Facades\DB;

final readonly class ResetForgottenPassword
{
    public function __construct(
        private UsablePasswordResetLinkFinder $links,
        private ApplyNewPassword $applyPassword,
        private UserSessionTerminator $sessions,
        private AuthenticationEventRecorder $events,
    ) {}

    public function handle(string $token, string $newPassword): void
    {
        $user = DB::transaction(function () use ($token, $newPassword): User {
            $link = $this->links->findForUpdate($token);
            $this->applyPassword->handle($link->user, $newPassword);
            $link->delete();

            return $link->user;
        });

        $this->sessions->terminateAll($user);
        $this->events->forUser(AuthEventType::PasswordResetCompleted, $user);
    }
}
