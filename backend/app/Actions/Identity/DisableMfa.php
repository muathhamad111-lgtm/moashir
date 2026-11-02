<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Exceptions\Identity\MfaRejected;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

final readonly class DisableMfa
{
    public function __construct(private DisableTwoFactorAuthentication $disable, private AuthenticationEventRecorder $events) {}

    public function handle(User $user): void
    {
        if ($user->requiresMfa()) {
            throw MfaRejected::requiredForRole();
        }

        if (! $user->hasConfirmedTotp()) {
            throw MfaRejected::notEnrolled();
        }

        ($this->disable)($user);
        $this->events->forUser(AuthEventType::MfaDisabled, $user);
    }
}
