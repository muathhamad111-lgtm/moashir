<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\IdentityChangeDraft;
use App\Data\Identity\NationalIdEntry;
use App\Enums\Identity\IdentityChangeSource;
use App\Enums\Identity\NationalIdState;
use App\Models\Identity\User;
use App\Services\Identity\NationalIdAssigner;
use App\Support\ValueObjects\NationalId;
use Illuminate\Support\Facades\DB;

/**
 * An account holder enters their own first national ID. It waits for an identity reviewer's approval, except for
 * the Super Admin, whose number is saved directly and audited (operator decision).
 */
final readonly class ProvideOwnNationalId
{
    public function __construct(private NationalIdAssigner $assigner, private SubmitIdentityChange $submit) {}

    public function handle(User $user, NationalId $nationalId): NationalIdState
    {
        if (! $user->isSuperAdmin()) {
            $this->submit->handle($user, $user, IdentityChangeDraft::firstEntry($nationalId));

            return NationalIdState::Pending;
        }

        DB::transaction(function () use ($user, $nationalId): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assigner->assignFirst($locked, $nationalId, new NationalIdEntry($user, IdentityChangeSource::SuperAdminEntry));
        });
        $user->refresh();

        return NationalIdState::Provided;
    }
}
