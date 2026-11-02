<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\SessionClock;
use App\Services\Identity\UserSessionTerminator;

final readonly class RevokeOtherSessions
{
    public function __construct(
        private UserSessionTerminator $sessions,
        private SessionClock $clock,
        private AuthenticationEventRecorder $events,
    ) {}

    public function handle(User $user): int
    {
        $revoked = $this->sessions->terminateAllExcept($user, $this->clock->currentSessionId());
        $this->events->forUser(AuthEventType::SessionRevoked, $user, ['scope' => 'others', 'count' => $revoked]);

        return $revoked;
    }
}
