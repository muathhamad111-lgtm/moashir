<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Data\Audit\AuditLogFilters;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAuditLogs
{
    public function __construct(private AuditLogQuery $query) {}

    /**
     * @return LengthAwarePaginator<int, Audit>
     */
    public function handle(User $viewer, AuditLogFilters $filters): LengthAwarePaginator
    {
        return $this->query->for($viewer, $filters)
            ->with('user')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }
}
