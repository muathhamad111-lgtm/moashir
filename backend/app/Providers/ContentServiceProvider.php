<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Cms\CmsPageKey;
use App\Models\Cms\CmsPage;
use App\Models\Kpis\Kpi;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('cmsPage', static fn (string $value): CmsPage => CmsPage::query()
            ->where('key', in_array($value, CmsPageKey::values(), true) ? $value : '')
            ->firstOrFail());

        Route::bind('publishedKpi', static fn (string $value): Kpi => Kpi::query()
            ->published()
            ->where('slug', $value)
            ->firstOrFail());
    }
}
