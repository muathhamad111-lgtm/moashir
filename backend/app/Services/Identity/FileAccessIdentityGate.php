<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\NationalIdState;
use App\Exceptions\Identity\FileAccessRefused;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;

/**
 * An account that must provide a national ID opens no stamped file (evidence, attachments, bundles) until its
 * number is approved; the rest of the platform stays usable (operator decision, 2026-11-21). Accounts that are not
 * required are stamped with their name in place of the national ID.
 */
final readonly class FileAccessIdentityGate
{
    public function __construct(private AccountCompletionResolver $completion, private AuditRecorder $audit) {}

    public function assertMayOpen(User $viewer, StoredFilePurpose $purpose): void
    {
        $status = $this->completion->of($viewer)->nationalId;

        if (! $status->required || $status->state === NationalIdState::Provided) {
            return;
        }

        $this->audit->record(new AuditEntry(AuditEvent::FileAccessRefused, $viewer, [], [
            'reason' => 'national_id_required',
            'purpose' => $purpose->value,
            'national_id_status' => $status->state->value,
        ]));

        throw FileAccessRefused::nationalIdRequired($status->state);
    }
}
