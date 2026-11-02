<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Settings\AssessmentSettingsController;
use App\Http\Controllers\Api\V1\Settings\FinanceSettingsController;
use Illuminate\Support\Facades\Route;

$finance = 'can:'.Ability::ManageFinanceSettings->value;
$assessment = 'can:'.Ability::ManageAssessmentSettings->value;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->prefix('settings')->name('settings.')->group(function () use ($finance, $assessment): void {
    Route::get('finance', [FinanceSettingsController::class, 'show'])->middleware($finance)->name('finance.show');
    Route::put('finance', [FinanceSettingsController::class, 'update'])->middleware([$finance, 'throttle:sensitive'])->name('finance.update');
    Route::get('assessment', [AssessmentSettingsController::class, 'show'])->middleware($assessment)->name('assessment.show');
    Route::put('assessment', [AssessmentSettingsController::class, 'update'])->middleware([$assessment, 'throttle:sensitive'])->name('assessment.update');
});
