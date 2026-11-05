<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Contact\ContactInboxController;
use App\Http\Controllers\Api\V1\Contact\ContactMessageWorkflowController;
use App\Http\Controllers\Api\V1\Contact\ContactPersonalDataController;
use App\Http\Controllers\Api\V1\Contact\ContactSettingsController;
use App\Http\Controllers\Api\V1\Contact\MyContactMessageController;
use App\Http\Controllers\Api\V1\Contact\SubmitContactMessageController;
use App\Models\Contact\ContactMessage;
use Illuminate\Support\Facades\Route;

$messages = ContactMessage::class;
$signedIn = ['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'];

Route::post('contact-messages', SubmitContactMessageController::class)->middleware('throttle:contact-messages')->name('contact-messages.store');

Route::middleware($signedIn)->group(function () use ($messages): void {
    Route::prefix('me/contact-messages')->name('me.contact-messages.')->middleware("can:viewOwn,{$messages}")->group(function (): void {
        Route::get('/', [MyContactMessageController::class, 'index'])->name('index');
        Route::get('{message}', [MyContactMessageController::class, 'show'])->whereUuid('message')->name('show');
        Route::post('{message}/replies', [MyContactMessageController::class, 'followUp'])->whereUuid('message')->middleware('throttle:contact-follow-ups')->name('replies.store');
        Route::patch('{message}/seen', [MyContactMessageController::class, 'markSeen'])->whereUuid('message')->name('seen');
    });

    Route::prefix('admin/contact-messages')->name('admin.contact-messages.')->group(function () use ($messages): void {
        Route::get('/', [ContactInboxController::class, 'index'])->middleware("can:viewAny,{$messages}")->name('index');
        Route::get('assignees', [ContactInboxController::class, 'assignees'])->middleware("can:listAssignees,{$messages}")->name('assignees');

        Route::whereUuid('contactMessage')->group(function (): void {
            Route::get('{contactMessage}', [ContactInboxController::class, 'show'])->middleware('can:view,contactMessage')->name('show');
            Route::post('{contactMessage}/assign', [ContactMessageWorkflowController::class, 'assign'])->middleware(['can:manage,contactMessage', 'throttle:sensitive'])->name('assign');
            Route::post('{contactMessage}/notes', [ContactMessageWorkflowController::class, 'note'])->middleware(['can:manage,contactMessage', 'throttle:content-write'])->name('notes.store');
            Route::post('{contactMessage}/replies', [ContactMessageWorkflowController::class, 'reply'])->middleware(['can:manage,contactMessage', 'throttle:content-write'])->name('replies.store');
            Route::post('{contactMessage}/close', [ContactMessageWorkflowController::class, 'close'])->middleware(['can:manage,contactMessage', 'throttle:sensitive'])->name('close');
            Route::post('{contactMessage}/reopen', [ContactMessageWorkflowController::class, 'reopen'])->middleware(['can:manage,contactMessage', 'throttle:sensitive'])->name('reopen');
        });
    });

    Route::prefix('privacy/contact-messages')->name('privacy.contact-messages.')->middleware(["can:managePersonalData,{$messages}", 'throttle:exports'])->group(function (): void {
        Route::post('export', [ContactPersonalDataController::class, 'export'])->name('export');
        Route::post('erase', [ContactPersonalDataController::class, 'erase'])->name('erase');
    });

    Route::prefix('settings/contact-messages')->name('settings.contact-messages.')->middleware('can:'.Ability::ManageContactSettings->value)->group(function (): void {
        Route::get('/', [ContactSettingsController::class, 'show'])->name('show');
        Route::put('/', [ContactSettingsController::class, 'update'])->middleware('throttle:sensitive')->name('update');
    });
});
