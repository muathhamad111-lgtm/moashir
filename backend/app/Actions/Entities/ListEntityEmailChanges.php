<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\EmailChangeListFilters;
use App\Enums\Identity\EmailChangeStatus;
use App\Models\Identity\EmailChangeRequest;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEntityEmailChanges
{
    /**
     * @return LengthAwarePaginator<int, EmailChangeRequest>
     */
    public function handle(EmailChangeListFilters $filters): LengthAwarePaginator
    {
        return EmailChangeRequest::query()
            ->with('entity')
            ->whereNotNull('entity_id')
            ->whereNot('status', EmailChangeStatus::AwaitingOtp)
            ->when($filters->status instanceof EmailChangeStatus, static fn ($query) => $query->where('status', $filters->status))
            ->latest('id')
            ->paginate($filters->perPage);
    }
}
