<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Dashboards\DashboardController;
use App\Models\Assessments\Assessment;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current', 'can:'.Ability::ViewDashboard->value])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function (): void {
        Route::get('summary', [DashboardController::class, 'summary'])->name('summary');
        Route::get('action-center', [DashboardController::class, 'actionCenter'])->name('action-center');
        Route::get('overview', [DashboardController::class, 'overview'])
            ->middleware('can:'.Ability::ViewExecutiveOverview->value)
            ->name('overview');
        Route::get('assessor-overview', [DashboardController::class, 'assessorOverview'])
            ->middleware('can:viewAssignedRequests,'.Assessment::class)
            ->name('assessor-overview');
    });
