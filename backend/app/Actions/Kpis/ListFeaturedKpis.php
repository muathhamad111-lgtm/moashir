<?php

declare(strict_types=1);

namespace App\Actions\Kpis;

use App\Models\Cms\SiteSettings;
use App\Models\Kpis\Kpi;
use Illuminate\Database\Eloquent\Collection;

final class ListFeaturedKpis
{
    /**
     * @return Collection<int, Kpi>
     */
    public function handle(): Collection
    {
        $settings = SiteSettings::current();

        if (! $settings->show_featured_kpis) {
            return new Collection;
        }

        return Kpi::query()
            ->published()
            ->where('show_on_homepage', true)
            ->ordered()
            ->with(['category', 'type', 'image'])
            ->limit($settings->featured_kpi_limit)
            ->get();
    }
}
