<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\NationalIdEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\BlindIndexPurpose;
use App\Enums\Identity\IdentityChangeSource;
use App\Exceptions\Identity\NationalIdRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Identity\AccountNationalId;
use App\Support\ValueObjects\NationalId;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class NationalIdAssigner
{
    public function __construct(private BlindIndex $blindIndex, private AuditRecorder $audit, private IdentityHistory $history) {}

    public function assignFirst(User $user, NationalId $nationalId, NationalIdEntry $entry): void
    {
        if (AccountNationalId::isRecorded($user)) {
            throw NationalIdRejected::locked();
        }

        $this->save($user, $nationalId);
        $this->history->recordDirect($user, null, $nationalId->value, $entry);
        $this->audit->record(new AuditEntry(AuditEvent::UserNationalIdRecorded, $user, [], ['source' => $entry->source->value]));
    }

    /**
     * An invitee replaces the number an administrator entered at invite. Saved without approval; the caller
     * informs the reviewers. Returns false when the number is unchanged.
     */
    public function replaceAtAcceptance(User $invitee, NationalId $nationalId): bool
    {
        $previous = $invitee->national_id;

        if ($previous === $nationalId->value) {
            return false;
        }

        $this->save($invitee, $nationalId);
        $this->history->recordDirect($invitee, $previous, $nationalId->value, new NationalIdEntry($invitee, IdentityChangeSource::InvitationAcceptance));
        $this->audit->record(new AuditEntry(AuditEvent::UserNationalIdChangedAtAcceptance, $invitee));

        return true;
    }

    public function assertAvailable(NationalId $nationalId, User $owner): void
    {
        $taken = User::query()
            ->where('national_id_index', $this->blindIndex->of(BlindIndexPurpose::NationalId, $nationalId->value))
            ->whereKeyNot($owner->getKey())
            ->exists();

        if ($taken) {
            throw NationalIdRejected::unavailable();
        }
    }

    private function save(User $user, NationalId $nationalId): void
    {
        $this->assertAvailable($nationalId, $user);

        try {
            User::withoutAuditing(static fn (): bool => $user->forceFill(['national_id' => $nationalId->value])->save());
        } catch (UniqueConstraintViolationException) {
            throw NationalIdRejected::unavailable();
        }
    }
}
