<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Models\Criteria\CriteriaEditEvent;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The history of published criteria edits (structural publications and text corrections), newest first.
 */
final class ListCriteriaEditEvents
{
    /**
     * @return LengthAwarePaginator<int, CriteriaEditEvent>
     */
    public function handle(int $perPage): LengthAwarePaginator
    {
        return CriteriaEditEvent::query()
            ->with(['fromVersion', 'toVersion', 'editor:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
