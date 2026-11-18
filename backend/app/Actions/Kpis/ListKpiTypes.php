<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Models\Kpis\KpiType;
use Illuminate\Database\Eloquent\Collection;

final class ListKpiTypes
{
    /**
     * @return Collection<int, KpiType>
     */
    public function handle(): Collection
    {
        return KpiType::query()->listed()->get();
    }
}
