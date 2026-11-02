<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\PrivacyRequestFilters;
use App\Models\Entities\PrivacyRequest;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPrivacyRequests
{
    /**
     * @return LengthAwarePaginator<int, PrivacyRequest>
     */
    public function handle(PrivacyRequestFilters $filters): LengthAwarePaginator
    {
        $query = PrivacyRequest::query()->with(['entity', 'createdBy', 'decidedBy']);

        $exactFilters = ['status' => $filters->status, 'kind' => $filters->kind, 'entity_id' => $filters->entityId];

        foreach ($exactFilters as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        return $query->latest('id')->paginate($filters->perPage);
    }
}
