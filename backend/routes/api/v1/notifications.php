<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Notifications\NotificationStateController;
use App\Http\Controllers\Api\V1\Notifications\NotificationTypeController;
use App\Models\Notifications\Notification;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->group(function (): void {
    Route::prefix('notifications')->name('notifications.')->middleware('can:viewAny,'.Notification::class)->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('read-all', [NotificationStateController::class, 'readAll'])->name('read-all');
        Route::patch('{notification}/read', [NotificationStateController::class, 'read'])->whereUuid('notification')->name('read');
        Route::patch('{notification}/archive', [NotificationStateController::class, 'archive'])->whereUuid('notification')->name('archive');
    });

    Route::prefix('me/notification-preferences')->name('me.notification-preferences.')->middleware('can:'.Ability::Authenticated->value)->group(function (): void {
        Route::get('/', [NotificationPreferenceController::class, 'show'])->name('show');
        Route::put('/', [NotificationPreferenceController::class, 'update'])->middleware('throttle:sensitive')->name('update');
    });

    Route::get('settings/notifications/types', [NotificationTypeController::class, 'index'])
        ->middleware('can:viewCatalog,'.Notification::class)
        ->name('settings.notifications.types.index');
});
