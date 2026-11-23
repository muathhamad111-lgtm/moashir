<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOwnProfileChangeRequests
{
    /**
     * @return LengthAwarePaginator<int, EntityProfileChangeRequest>
     */
    public function handle(User $owner, int $perPage): LengthAwarePaginator
    {
        return EntityProfileChangeRequest::query()
            ->with(['entity', 'submitter', 'reviewer'])
            ->where('entity_id', $owner->entity_id)
            ->latest('id')
            ->paginate($perPage);
    }
}
