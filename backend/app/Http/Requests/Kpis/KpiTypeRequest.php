<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Models\Kpis\KpiType;

final class KpiTypeRequest extends KpiCatalogRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'description' => ['prohibited'], 'icon' => ['prohibited']];
    }

    protected function table(): string
    {
        return 'kpi_types';
    }

    protected function keyLength(): int
    {
        return 40;
    }

    protected function current(): ?KpiType
    {
        $type = $this->route('type');

        return $type instanceof KpiType ? $type : null;
    }

    protected function usesSoftDeletes(): bool
    {
        return false;
    }
}
