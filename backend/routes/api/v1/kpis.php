<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Kpis\KpiCategoryController;
use App\Http\Controllers\Api\V1\Kpis\KpiController;
use App\Http\Controllers\Api\V1\Kpis\KpiImageController;
use App\Http\Controllers\Api\V1\Kpis\KpiOrderController;
use App\Http\Controllers\Api\V1\Kpis\KpiPublicationController;
use App\Http\Controllers\Api\V1\Kpis\KpiTypeController;
use App\Http\Controllers\Api\V1\Kpis\KpiValueController;
use App\Http\Controllers\Api\V1\Kpis\PublicKpiCatalogController;
use App\Http\Controllers\Api\V1\Kpis\PublicKpiController;
use App\Models\Kpis\Kpi;
use Illuminate\Support\Facades\Route;

$manage = 'can:manage,'.Kpi::class;

Route::middleware('throttle:public')->group(function (): void {
    Route::get('kpis', [PublicKpiController::class, 'index'])->name('kpis.index');
    Route::get('kpis/featured', [PublicKpiController::class, 'featured'])->name('kpis.featured');
    Route::get('kpis/{publishedKpi}', [PublicKpiController::class, 'show'])->where('publishedKpi', '[a-z0-9]+(-[a-z0-9]+)*')->name('kpis.show');
    Route::get('kpi-categories', [PublicKpiCatalogController::class, 'categories'])->name('kpi-categories.index');
    Route::get('kpi-types', [PublicKpiCatalogController::class, 'types'])->name('kpi-types.index');
});

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current', $manage])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('kpis', [KpiController::class, 'index'])->name('kpis.index');
        Route::post('kpis', [KpiController::class, 'store'])->middleware('throttle:content-write')->name('kpis.store');
        Route::patch('kpis/reorder', [KpiOrderController::class, 'reorder'])->middleware('throttle:content-write')->name('kpis.reorder');
        Route::post('kpis/images', KpiImageController::class)->middleware('throttle:uploads')->name('kpis.images.store');

        Route::whereNumber(['kpi', 'category', 'type'])->group(function (): void {
            Route::get('kpis/{kpi}', [KpiController::class, 'show'])->name('kpis.show');
            Route::put('kpis/{kpi}', [KpiController::class, 'update'])->middleware('throttle:content-write')->name('kpis.update');
            Route::delete('kpis/{kpi}', [KpiController::class, 'destroy'])->middleware('throttle:content-write')->name('kpis.destroy');
            Route::patch('kpis/{kpi}/published', KpiPublicationController::class)->middleware('throttle:sensitive')->name('kpis.published');
            Route::patch('kpis/{kpi}/reorder', [KpiOrderController::class, 'move'])->middleware('throttle:content-write')->name('kpis.move');
            Route::get('kpis/{kpi}/values', [KpiValueController::class, 'index'])->name('kpis.values.index');
            Route::post('kpis/{kpi}/values', [KpiValueController::class, 'store'])->middleware('throttle:content-write')->name('kpis.values.store');

            Route::get('kpi-categories', [KpiCategoryController::class, 'index'])->name('kpi-categories.index');
            Route::post('kpi-categories', [KpiCategoryController::class, 'store'])->middleware('throttle:content-write')->name('kpi-categories.store');
            Route::put('kpi-categories/{category}', [KpiCategoryController::class, 'update'])->middleware('throttle:content-write')->name('kpi-categories.update');
            Route::delete('kpi-categories/{category}', [KpiCategoryController::class, 'destroy'])->middleware('throttle:content-write')->name('kpi-categories.destroy');

            Route::get('kpi-types', [KpiTypeController::class, 'index'])->name('kpi-types.index');
            Route::post('kpi-types', [KpiTypeController::class, 'store'])->middleware('throttle:content-write')->name('kpi-types.store');
            Route::put('kpi-types/{type}', [KpiTypeController::class, 'update'])->middleware('throttle:content-write')->name('kpi-types.update');
            Route::delete('kpi-types/{type}', [KpiTypeController::class, 'destroy'])->middleware('throttle:content-write')->name('kpi-types.destroy');
        });
    });
