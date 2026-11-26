<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\Identity\IdentityChangeSource;
use App\Enums\Identity\IdentityField;
use App\Models\Identity\UserIdentityChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Relations of an account to its append-only identity history.
 */
trait HasIdentityHistory
{
    /**
     * The latest national ID an invitee changed at acceptance, shown to administrators on the staff record.
     *
     * @return HasOne<UserIdentityChange, $this>
     */
    public function acceptanceNationalIdChange(): HasOne
    {
        return $this->hasOne(UserIdentityChange::class)->ofMany(['id' => 'max'], static function (Builder $query): void {
            $query->where('field', IdentityField::NationalId)->where('source', IdentityChangeSource::InvitationAcceptance);
        });
    }
}
