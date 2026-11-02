<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\SessionClock;
use App\Services\Identity\UserSessionTerminator;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

final readonly class ChangeOwnPassword
{
    public function __construct(
        private ApplyNewPassword $applyPassword,
        private UserSessionTerminator $sessions,
        private SessionClock $clock,
        private AuthenticationEventRecorder $events,
        private AuthFactory $auth,
    ) {}

    public function handle(User $user, string $newPassword): void
    {
        $this->applyPassword->handle($user, $newPassword);
        $this->sessions->terminateAllExcept($user, $this->clock->currentSessionId());
        $this->auth->guard('web')->login($user);
        $this->events->forUser(AuthEventType::PasswordChanged, $user);
    }
}
