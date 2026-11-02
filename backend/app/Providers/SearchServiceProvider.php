<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Assessments\RequestSearchProvider;
use App\Services\Entities\EntitySearchProvider;
use App\Services\Identity\UserSearchProvider;
use App\Services\Search\SearchProviderRegistry;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            EntitySearchProvider::class,
            RequestSearchProvider::class,
            UserSearchProvider::class,
        ], SearchProviderRegistry::TAG);
    }
}
