<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Identity\EntityAccounts;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\PrivacyRequest;
use App\Models\Entities\Region;
use App\Models\Identity\EmailChangeRequest;
use App\Services\Entities\EntityAccountDirectory;
use App\Services\Entities\EntityEraser;
use App\Services\Entities\EntityLogoEraser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class EntitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EntityAccounts::class, EntityAccountDirectory::class);
        $this->app->tag([EntityLogoEraser::class], EntityEraser::TAG);
    }

    public function boot(): void
    {
        Route::bind('entity', static fn (string $value): Entity => Entity::withTrashed()
            ->notErased()
            ->whereKey((int) $value)
            ->firstOrFail());

        Route::bind('emailChange', static fn (string $value): EmailChangeRequest => EmailChangeRequest::query()
            ->with('entity')
            ->whereNotNull('entity_id')
            ->whereKey((int) $value)
            ->firstOrFail());

        Route::bind('privacyRequest', static fn (string $value): PrivacyRequest => PrivacyRequest::query()->whereKey((int) $value)->firstOrFail());
        Route::bind('region', static fn (string $value): Region => Region::query()->whereKey((int) $value)->firstOrFail());
        Route::bind('city', static fn (string $value): City => City::query()->whereKey((int) $value)->firstOrFail());
    }
}
