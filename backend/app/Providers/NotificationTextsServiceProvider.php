<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Notifications\SmsTextApprovals;
use App\Services\Notifications\Templates\NotificationTextOverrides;
use App\Services\Notifications\Templates\ShippedNotificationTemplates;
use App\Translation\NotificationTextOverrideLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;

final class NotificationTextsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShippedNotificationTemplates::class);
        $this->app->scoped(SmsTextApprovals::class);

        $this->app->extend('translation.loader', static fn (Loader $loader, Application $app): Loader => new NotificationTextOverrideLoader(
            $loader,
            $app->make(NotificationTextOverrides::class),
        ));
    }
}
