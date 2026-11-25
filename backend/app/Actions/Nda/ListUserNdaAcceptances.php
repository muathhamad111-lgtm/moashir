<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The agreement versions a user accepted, newest first: for their own account page and for the staff page.
 */
final class ListUserNdaAcceptances
{
    /**
     * @return LengthAwarePaginator<int, TermsAcceptance>
     */
    public function handle(User $user, int $perPage): LengthAwarePaginator
    {
        return TermsAcceptance::query()
            ->where('user_id', $user->id)
            ->whereHas('termsVersion', static fn (Builder $version): Builder => $version->where('kind', TermsKind::Nda->value))
            ->with('termsVersion:id,version,title,effective_from')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }
}
