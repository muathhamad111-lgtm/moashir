<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Contact\ContactMessage;
use App\Services\Contact\ContactMessageEraser;
use App\Services\Entities\EntityEraser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ContactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ContactMessageEraser::class], EntityEraser::TAG);
    }

    public function boot(): void
    {
        Route::bind('contactMessage', static fn (string $value): ContactMessage => ContactMessage::query()
            ->where('public_id', $value)
            ->firstOrFail());
    }
}
