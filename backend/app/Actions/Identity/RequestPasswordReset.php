<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetLinkNotification;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\PasswordResetTokens;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\SignInIdentifier;

final readonly class RequestPasswordReset
{
    public function __construct(
        private PasswordResetTokens $tokens,
        private AuthenticationEventRecorder $events,
    ) {}

    public function handle(EmailAddress $email): void
    {
        $user = User::query()->withEmail($email)->first();

        if ($user === null || $user->status !== UserStatus::Active) {
            $this->events->forUnknownIdentifier(AuthEventType::PasswordResetRequested, SignInIdentifier::ofEmail($email));

            return;
        }

        if ($this->tokens->wasRecentlyIssued($user)) {
            return;
        }

        $user->notify(new PasswordResetLinkNotification($this->tokens->issue($user), $this->tokens->validMinutes()));
        $this->events->forUser(AuthEventType::PasswordResetRequested, $user);
    }
}
