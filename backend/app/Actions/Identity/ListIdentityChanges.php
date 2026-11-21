<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\IdentityChangeListFilters;
use App\Enums\Identity\IdentityChangeStatus;
use App\Models\Identity\IdentityChangeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListIdentityChanges
{
    /**
     * @return LengthAwarePaginator<int, IdentityChangeRequest>
     */
    public function handle(IdentityChangeListFilters $filters): LengthAwarePaginator
    {
        return IdentityChangeRequest::query()
            ->with(['user', 'submitter', 'reviewer'])
            ->when($filters->status instanceof IdentityChangeStatus, static fn (Builder $query): Builder => $query->where('status', $filters->status))
            ->latest('id')
            ->paginate($filters->perPage);
    }
}
