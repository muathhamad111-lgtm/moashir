<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Nda\ExportNdaAcceptancesController;
use App\Http\Controllers\Api\V1\Nda\MyNdaAcceptanceController;
use App\Http\Controllers\Api\V1\Nda\MyNdaController;
use App\Http\Controllers\Api\V1\Nda\NdaAcceptanceController;
use App\Http\Controllers\Api\V1\Nda\NdaDraftController;
use App\Http\Controllers\Api\V1\Nda\NdaImpactController;
use App\Http\Controllers\Api\V1\Nda\NdaSettingsController;
use App\Http\Controllers\Api\V1\Nda\NdaVersionController;
use App\Http\Controllers\Api\V1\Nda\PublishNdaController;
use App\Http\Controllers\Api\V1\Nda\SendNdaRemindersController;
use App\Http\Controllers\Api\V1\Nda\UserNdaAcceptanceController;
use Illuminate\Support\Facades\Route;

$authenticated = 'can:'.Ability::Authenticated->value;
$manage = 'can:'.Ability::ManageNda->value;

// The agreement itself stays reachable while the gate (`nda.current`) refuses every other signed-in route.
Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current', $authenticated])
    ->prefix('me/nda')
    ->name('me.nda.')
    ->group(function (): void {
        Route::get('/', [MyNdaController::class, 'show'])->name('show');
        Route::post('acceptances', [MyNdaController::class, 'store'])->middleware('throttle:sensitive')->name('accept');
    });

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current', 'nda.current'])->group(function () use ($authenticated, $manage): void {
    Route::prefix('me/nda')->name('me.nda.')->middleware($authenticated)->group(function (): void {
        Route::get('acceptances', [MyNdaAcceptanceController::class, 'index'])->name('acceptances.index');
        Route::get('versions/{ndaVersion}', [MyNdaAcceptanceController::class, 'show'])
            ->middleware('can:'.Ability::ViewAcceptedNda->value.',ndaVersion')
            ->name('versions.show');
    });

    Route::get('users/{managedUser}/nda-acceptances', UserNdaAcceptanceController::class)->middleware('can:view,managedUser')->name('users.nda-acceptances.index');

    Route::prefix('settings/nda')->name('settings.nda.')->middleware($manage)->group(function (): void {
        Route::get('/', [NdaSettingsController::class, 'show'])->name('show');
        Route::put('/', [NdaSettingsController::class, 'update'])->middleware('throttle:sensitive')->name('update');
        Route::get('versions', [NdaVersionController::class, 'index'])->name('versions.index');
        Route::get('versions/{ndaVersion}', [NdaVersionController::class, 'show'])->name('versions.show');
        Route::post('draft', [NdaDraftController::class, 'store'])->middleware('throttle:content-write')->name('draft.store');
        Route::put('draft', [NdaDraftController::class, 'update'])->middleware('throttle:content-write')->name('draft.update');
        Route::delete('draft', [NdaDraftController::class, 'destroy'])->middleware('throttle:content-write')->name('draft.destroy');
        Route::get('draft/impact', NdaImpactController::class)->name('draft.impact');
        Route::post('draft/publish', PublishNdaController::class)->middleware('throttle:sensitive')->name('draft.publish');
        Route::get('acceptances', [NdaAcceptanceController::class, 'index'])->name('acceptances.index');
        Route::get('acceptances/export', ExportNdaAcceptancesController::class)->middleware(['throttle:sensitive', 'throttle:exports'])->name('acceptances.export');
        Route::post('reminders', SendNdaRemindersController::class)->middleware('throttle:sensitive')->name('reminders.store');
    });
});
