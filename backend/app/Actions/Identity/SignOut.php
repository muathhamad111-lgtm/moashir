<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;

final readonly class SignOut
{
    public function __construct(
        private AuthFactory $auth,
        private Session $session,
        private AuthenticationEventRecorder $events,
    ) {}

    public function handle(User $user, AuthEventType $reason = AuthEventType::Logout): void
    {
        $this->events->forUser($reason, $user);

        $this->auth->guard('web')->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }
}
