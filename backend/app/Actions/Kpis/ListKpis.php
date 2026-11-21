<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Data\Kpis\KpiListFilters;
use App\Models\Kpis\Kpi;
use App\Services\Kpis\KpiListQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListKpis
{
    public function __construct(private KpiListQuery $kpis) {}

    /**
     * @return LengthAwarePaginator<int, Kpi>
     */
    public function handle(KpiListFilters $filters, string $locale): LengthAwarePaginator
    {
        return $this->kpis->filtered($filters, $locale)
            ->with(['category', 'type', 'image', 'updatedBy'])
            ->paginate($filters->perPage);
    }
}
