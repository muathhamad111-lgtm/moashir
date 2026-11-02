<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Models\Identity\User;
use App\Models\Identity\UserSession;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\UserSessionTerminator;

final readonly class RevokeOwnSession
{
    public function __construct(private UserSessionTerminator $sessions, private AuthenticationEventRecorder $events) {}

    public function handle(User $user, UserSession $session): void
    {
        $this->sessions->terminate($session);
        $this->events->forUser(AuthEventType::SessionRevoked, $user, ['scope' => 'single']);
    }
}
