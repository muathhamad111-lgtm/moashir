<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Enums\Identity\UserStatus;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The active staff accounts the agreement applies to, and those of them still to accept a given version.
 */
final readonly class NdaAudience
{
    public function __construct(private NdaScope $scope) {}

    /**
     * @return Builder<User>
     */
    public function inScope(): Builder
    {
        $roles = $this->scope->roleValues();

        return User::query()
            ->staff()
            ->where('status', UserStatus::Active)
            ->where(static fn (Builder $query): Builder => $query
                ->where('is_super_admin', true)
                ->orWhereHas('roles', static fn (Builder $role): Builder => $role->whereIn('name', $roles)));
    }

    /**
     * @return Builder<User>
     */
    public function pending(TermsVersion $version): Builder
    {
        return $this->inScope()->whereDoesntHave(
            'termsAcceptances',
            static fn (Builder $acceptance): Builder => $acceptance->where('terms_version_id', $version->id),
        );
    }
}
