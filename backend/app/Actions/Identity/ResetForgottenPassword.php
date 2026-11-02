<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Exceptions\Identity\AccountActionRejected;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\PasswordResetTokens;
use App\Services\Identity\UserSessionTerminator;
use App\Support\ValueObjects\EmailAddress;

final readonly class ResetForgottenPassword
{
    public function __construct(
        private PasswordResetTokens $tokens,
        private ApplyNewPassword $applyPassword,
        private UserSessionTerminator $sessions,
        private AuthenticationEventRecorder $events,
    ) {}

    public function handle(EmailAddress $email, string $token, string $newPassword): void
    {
        $user = User::query()->withEmail($email)->first();

        if ($user === null || ! $this->tokens->isValid($user, $token)) {
            throw AccountActionRejected::invalidToken();
        }

        $this->applyPassword->handle($user, $newPassword);
        $this->tokens->revoke($user);
        $this->sessions->terminateAll($user);
        $this->events->forUser(AuthEventType::PasswordResetCompleted, $user);
    }
}
