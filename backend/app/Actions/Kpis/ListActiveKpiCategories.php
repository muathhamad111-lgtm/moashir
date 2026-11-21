<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Models\Kpis\KpiCategory;
use Illuminate\Database\Eloquent\Collection;

final class ListActiveKpiCategories
{
    /**
     * @return Collection<int, KpiCategory>
     */
    public function handle(): Collection
    {
        return KpiCategory::query()->where('is_active', true)->listed()->get();
    }
}
