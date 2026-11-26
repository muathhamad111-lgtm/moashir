<?php

declare(strict_types=1);

namespace App\Services\Demo;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Notification;

final readonly class DemoNotificationsMute
{
    public function __construct(private Application $app) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public function during(Closure $work): mixed
    {
        $dispatcher = Notification::getFacadeRoot();
        Notification::swap(new DiscardedNotifications($this->app));

        try {
            return $work();
        } finally {
            Notification::swap($dispatcher);
        }
    }
}
