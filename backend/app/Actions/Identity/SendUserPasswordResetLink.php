<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Identity\AccountActionRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetLinkNotification;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\PasswordResetTokens;

/**
 * An administrator e-mails an active staff user the same single-use reset link
 * the user would get from "forgot password"; the password itself never passes through the administrator.
 */
final readonly class SendUserPasswordResetLink
{
    public function __construct(
        private PasswordResetTokens $tokens,
        private AuthenticationEventRecorder $events,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, User $target): void
    {
        $this->ensureSendable($actor, $target);

        $target->notify(new PasswordResetLinkNotification($this->tokens->issue($target), $this->tokens->validMinutes()));
        $this->events->forUser(AuthEventType::PasswordResetRequested, $target);
        $this->audit->record(new AuditEntry(AuditEvent::UserPasswordResetLinkSent, $target));
    }

    private function ensureSendable(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw AccountActionRejected::selfTarget();
        }

        if ($target->status !== UserStatus::Active) {
            throw IllegalStateTransition::from($target->effectiveStatus()->value, 'send_password_reset');
        }

        if ($this->tokens->wasRecentlyIssued($target)) {
            throw AccountActionRejected::passwordResetRecentlySent();
        }
    }
}
