<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Audit\ExportAuditLogsController;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->prefix('audit-logs')->name('audit-logs.')->group(function (): void {
    Route::get('/', [AuditLogController::class, 'index'])->middleware('can:viewAny,'.Audit::class)->name('index');
    Route::post('export', ExportAuditLogsController::class)->middleware(['can:export,'.Audit::class, 'throttle:exports'])->name('export');
    Route::get('{audit}', [AuditLogController::class, 'show'])->whereNumber('audit')->middleware('can:view,audit')->name('show');
});
