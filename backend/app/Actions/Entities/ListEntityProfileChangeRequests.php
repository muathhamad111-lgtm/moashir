<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\ProfileChangeListFilters;
use App\Enums\Entities\ProfileChangeStatus;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEntityProfileChangeRequests
{
    /**
     * @return LengthAwarePaginator<int, EntityProfileChangeRequest>
     */
    public function handle(ProfileChangeListFilters $filters): LengthAwarePaginator
    {
        return EntityProfileChangeRequest::query()
            ->with(['entity', 'submitter', 'reviewer'])
            ->whereHas('entity', static fn (Builder $entity): Builder => $entity->notErased())
            ->when($filters->status instanceof ProfileChangeStatus, static fn (Builder $query): Builder => $query->where('status', $filters->status))
            ->when($filters->entityId !== null, static fn (Builder $query): Builder => $query->where('entity_id', $filters->entityId))
            ->when($filters->search, static fn (Builder $query, SearchTerm $search): Builder => $query->whereHas('entity', static fn (Builder $entity): Builder => $entity->matchingLookup($search)))
            ->latest('id')
            ->paginate($filters->perPage);
    }
}
