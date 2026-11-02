<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Identity\MfaRejected;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\PendingAuthentication;
use App\Services\Notifications\SecurityAlerter;

final readonly class RecordFailedMfa
{
    public function __construct(
        private PendingAuthentication $pending,
        private AuthenticationEventRecorder $events,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(User $user): never
    {
        $this->events->forUser(AuthEventType::MfaFailed, $user);

        if ($user->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::SuperAdminMfaFailed);
        }

        if ($this->pending->recordFailure() >= PendingAuthentication::MAX_FAILURES) {
            $this->pending->clear();

            throw MfaRejected::attemptsExceeded();
        }

        throw MfaRejected::invalidCode();
    }
}
