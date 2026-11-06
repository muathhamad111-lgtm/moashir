<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Dashboards\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current', 'can:'.Ability::ViewDashboard->value])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function (): void {
        Route::get('summary', [DashboardController::class, 'summary'])->name('summary');
        Route::get('action-center', [DashboardController::class, 'actionCenter'])->name('action-center');
    });
