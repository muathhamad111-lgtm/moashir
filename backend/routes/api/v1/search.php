<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Search\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->group(function (): void {
    Route::get('search', GlobalSearchController::class)
        ->middleware(['can:'.Ability::GlobalSearch->value, 'throttle:search'])
        ->name('search');
});
