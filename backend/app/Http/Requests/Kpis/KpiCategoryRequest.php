<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Models\Kpis\KpiCategory;

final class KpiCategoryRequest extends KpiCatalogRequest
{
    protected function table(): string
    {
        return 'kpi_categories';
    }

    protected function keyLength(): int
    {
        return 60;
    }

    protected function current(): ?KpiCategory
    {
        $category = $this->route('category');

        return $category instanceof KpiCategory ? $category : null;
    }

    protected function usesSoftDeletes(): bool
    {
        return true;
    }
}
