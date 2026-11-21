<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

final class StoreKpiRequest extends KpiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->kpiRules(false);
    }
}
