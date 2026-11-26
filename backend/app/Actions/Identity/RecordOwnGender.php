<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\Gender;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;

/**
 * A staff account completes its gender from the completion modal. The audit row says it was recorded, never the
 * value (personal data under PDPL).
 */
final readonly class RecordOwnGender
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, Gender $gender): void
    {
        $user->gender = $gender;
        $user->save();

        $this->audit->record(new AuditEntry(AuditEvent::UserGenderRecorded, $user, [], ['by' => 'self']));
    }
}
