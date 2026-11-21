<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Periods\AssessmentPeriodController;
use App\Models\Assessments\AssessmentPeriod;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])
    ->prefix('assessment-periods')
    ->name('assessment-periods.')
    ->group(function (): void {
        Route::get('/', [AssessmentPeriodController::class, 'index'])->middleware('can:viewAny,'.AssessmentPeriod::class)->name('index');
    });
