<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RequestContext::class);
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        JsonResource::withoutWrapping();
        Sanctum::getAccessTokenFromRequestUsing(static fn (): ?string => null);
    }
}
