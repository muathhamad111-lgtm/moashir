<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Actions\Identity\SubmitIdentityChange;
use App\Data\Identity\IdentityChangeDraft;
use App\Models\Identity\User;
use App\Support\Identity\AccountNationalId;
use App\Support\ValueObjects\NationalId;

/**
 * The national ID an invitee gives at acceptance (operator decision, 2026-11-21): blank keeps what the
 * administrator entered, or leaves the number for later; a different number replaces the administrator's entry
 * without approval and informs the identity reviewers; a first number waits for approval like any self-entry.
 */
final readonly class InviteeNationalId
{
    public function __construct(
        private NationalIdAssigner $assigner,
        private SubmitIdentityChange $submit,
        private IdentityChangeNotifier $notifier,
    ) {}

    public function record(User $invitee, ?NationalId $entered): void
    {
        if (! $entered instanceof NationalId) {
            return;
        }

        if (! AccountNationalId::isRecorded($invitee)) {
            $this->submit->handle($invitee, $invitee, IdentityChangeDraft::firstEntry($entered));

            return;
        }

        if ($this->assigner->replaceAtAcceptance($invitee, $entered)) {
            $this->notifier->nationalIdChangedAtAcceptance($invitee);
        }
    }
}
