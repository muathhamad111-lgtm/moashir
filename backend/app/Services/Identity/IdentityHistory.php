<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\NationalIdEntry;
use App\Enums\Identity\IdentityChangeSource;
use App\Enums\Identity\IdentityField;
use App\Models\Identity\User;
use App\Models\Identity\UserIdentityChange;

/**
 * Writes the append-only identity history for values saved without an approval request.
 */
final readonly class IdentityHistory
{
    public function recordDirect(User $user, ?string $oldNationalId, string $newNationalId, NationalIdEntry $entry): void
    {
        UserIdentityChange::query()->create([
            'user_id' => $user->id,
            'identity_change_request_id' => null,
            'field' => IdentityField::NationalId,
            'old_value' => $oldNationalId,
            'new_value' => $newNationalId,
            'requested_by_user_id' => $entry->actor->id,
            'approved_by_user_id' => $entry->source === IdentityChangeSource::AdminEntry ? $entry->actor->id : null,
            'source' => $entry->source,
        ]);
    }
}
