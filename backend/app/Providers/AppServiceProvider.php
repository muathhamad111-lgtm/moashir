<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Platform\Readiness\DatabaseReadiness;
use App\Services\Platform\Readiness\DiskSpaceReadiness;
use App\Services\Platform\Readiness\MalwareScannerReadiness;
use App\Services\Platform\Readiness\PdfRendererReadiness;
use App\Services\Platform\Readiness\QueueReadiness;
use App\Services\Platform\Readiness\ReadinessProbe;
use App\Services\Platform\Readiness\RedisReadiness;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    private const array READINESS_CHECKS = [
        DatabaseReadiness::class,
        RedisReadiness::class,
        QueueReadiness::class,
        DiskSpaceReadiness::class,
        MalwareScannerReadiness::class,
        PdfRendererReadiness::class,
    ];

    public function register(): void
    {
        $this->app->scoped(RequestContext::class);
        $this->app->tag(self::READINESS_CHECKS, ReadinessProbe::TAG);
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
