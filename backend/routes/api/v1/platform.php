<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Platform\HealthController;
use App\Http\Controllers\Api\V1\Platform\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->middleware('throttle:public')->name('health');
Route::get('health/ready', ReadinessController::class)->middleware('throttle:public')->name('health.ready');
