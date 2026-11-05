<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use App\Services\Identity\AccountLockout;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Support\ValueObjects\SignInIdentifier;

final readonly class RecordFailedSignIn
{
    public function __construct(private AccountLockout $lockout, private AuthenticationEventRecorder $events) {}

    public function handle(SignInIdentifier $identifier, ?User $user): never
    {
        if (! $user instanceof User) {
            $this->events->forUnknownIdentifier(AuthEventType::LoginFailed, $identifier);

            throw AuthenticationRejected::invalidCredentials();
        }

        $this->events->forUser(AuthEventType::LoginFailed, $user, ['identifier_type' => $identifier->type->value]);

        if ($this->lockout->recordFailure($user)) {
            throw $this->lockout->rejection();
        }

        throw AuthenticationRejected::invalidCredentials();
    }
}
