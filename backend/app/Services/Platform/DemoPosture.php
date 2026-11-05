<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final readonly class DemoPosture
{
    private const array DEVELOPMENT_ENVIRONMENTS = ['local', 'testing'];

    public function __construct(private Application $app, private Repository $config) {}

    public function isOptedIn(): bool
    {
        return $this->config->get('moashir.demo.posture') === true;
    }

    public function allowsDemoTooling(): bool
    {
        return $this->app->environment(self::DEVELOPMENT_ENVIRONMENTS) || $this->isOptedIn();
    }

    public function isRealProduction(): bool
    {
        return $this->app->isProduction() && ! $this->isOptedIn();
    }
}
