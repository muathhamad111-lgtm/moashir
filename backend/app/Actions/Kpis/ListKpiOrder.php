<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Models\Kpis\Kpi;
use Illuminate\Database\Eloquent\Collection;

final class ListKpiOrder
{
    /**
     * @return Collection<int, Kpi>
     */
    public function handle(): Collection
    {
        return Kpi::query()->ordered()->get();
    }
}
