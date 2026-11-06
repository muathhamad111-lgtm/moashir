<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Mail\MailFrameComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class NotificationMailServiceProvider extends ServiceProvider
{
    public const string TEMPLATE = 'notifications::email';

    public function boot(): void
    {
        View::composer(self::TEMPLATE, MailFrameComposer::class);
    }
}
