<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Models\Kpis\Kpi;

final class SummarizeKpis
{
    /**
     * @return array{total: int, published: int, unpublished: int, featured: int}
     */
    public function handle(): array
    {
        $total = Kpi::query()->count();
        $published = Kpi::query()->published()->count();

        return [
            'total' => $total,
            'published' => $published,
            'unpublished' => $total - $published,
            'featured' => Kpi::query()->where('show_on_homepage', true)->count(),
        ];
    }
}
