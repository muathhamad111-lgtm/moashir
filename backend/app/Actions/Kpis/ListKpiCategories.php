<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Models\Kpis\KpiCategory;
use Illuminate\Database\Eloquent\Collection;

final class ListKpiCategories
{
    /**
     * @return Collection<int, KpiCategory>
     */
    public function handle(): Collection
    {
        return KpiCategory::query()->withCount('kpis')->listed()->get();
    }
}
