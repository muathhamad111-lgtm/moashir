<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\BlindIndexPurpose;
use App\Exceptions\Identity\NationalIdRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Identity\AccountNationalId;
use App\Support\ValueObjects\NationalId;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class NationalIdAssigner
{
    public function __construct(private BlindIndex $blindIndex, private AuditRecorder $audit) {}

    public function assignFirst(User $user, NationalId $nationalId): void
    {
        if (AccountNationalId::isRecorded($user)) {
            throw NationalIdRejected::locked();
        }

        $this->assertAvailable($nationalId, $user);

        try {
            User::withoutAuditing(static fn (): bool => $user->forceFill(['national_id' => $nationalId->value])->save());
        } catch (UniqueConstraintViolationException) {
            throw NationalIdRejected::unavailable();
        }

        $this->audit->record(new AuditEntry(AuditEvent::UserNationalIdRecorded, $user));
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
}
