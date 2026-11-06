<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Entities\CityController;
use App\Http\Controllers\Api\V1\Entities\ConfirmMyEmailChangeController;
use App\Http\Controllers\Api\V1\Entities\CurrentTermsController;
use App\Http\Controllers\Api\V1\Entities\DeactivateEntityController;
use App\Http\Controllers\Api\V1\Entities\EntityController;
use App\Http\Controllers\Api\V1\Entities\EntityDeletionImpactController;
use App\Http\Controllers\Api\V1\Entities\EntityEmailChangeController;
use App\Http\Controllers\Api\V1\Entities\EntityProfileChangeController;
use App\Http\Controllers\Api\V1\Entities\ExportEntitiesController;
use App\Http\Controllers\Api\V1\Entities\ExportEntityPersonalDataController;
use App\Http\Controllers\Api\V1\Entities\MyEmailChangeController;
use App\Http\Controllers\Api\V1\Entities\MyEntityController;
use App\Http\Controllers\Api\V1\Entities\MyEntityLogoController;
use App\Http\Controllers\Api\V1\Entities\MyProfileChangeController;
use App\Http\Controllers\Api\V1\Entities\PermanentlyDeleteEntityController;
use App\Http\Controllers\Api\V1\Entities\PrivacyEntityLookupController;
use App\Http\Controllers\Api\V1\Entities\PrivacyRequestController;
use App\Http\Controllers\Api\V1\Entities\ReactivateEntityController;
use App\Http\Controllers\Api\V1\Entities\RegionController;
use App\Http\Controllers\Api\V1\Entities\RegisterEntityController;
use App\Http\Controllers\Api\V1\Entities\ResendEntityVerificationController;
use App\Http\Controllers\Api\V1\Entities\RestoreEntityController;
use App\Http\Controllers\Api\V1\Entities\SoftDeleteEntityController;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\EmailChangeRequest;
use Illuminate\Support\Facades\Route;

$entities = Entity::class;
$cities = City::class;
$emailChanges = EmailChangeRequest::class;
$privacyRequests = PrivacyRequest::class;

Route::get('regions', [RegionController::class, 'index'])->middleware('throttle:public')->name('regions.index');
Route::get('terms/current', CurrentTermsController::class)->middleware('throttle:public')->name('terms.current');
Route::post('auth/register', RegisterEntityController::class)->middleware('throttle:register')->name('auth.register');

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->group(function () use ($entities, $cities, $emailChanges, $privacyRequests): void {
    Route::prefix('entities/me')->name('entities.me.')->group(function () use ($entities): void {
        Route::get('/', [MyEntityController::class, 'show'])->middleware("can:viewOwn,{$entities}")->name('show');
        Route::put('/', [MyEntityController::class, 'update'])->middleware(["can:updateOwn,{$entities}", 'throttle:sensitive'])->name('update');
        Route::post('logo', [MyEntityLogoController::class, 'store'])->middleware(["can:updateOwn,{$entities}", 'throttle:uploads'])->name('logo.store');
        Route::delete('logo', [MyEntityLogoController::class, 'destroy'])->middleware(["can:updateOwn,{$entities}", 'throttle:sensitive'])->name('logo.destroy');
        Route::get('profile-changes', MyProfileChangeController::class)->middleware("can:viewOwn,{$entities}")->name('profile-changes');
        Route::post('email/change', MyEmailChangeController::class)->middleware(["can:updateOwn,{$entities}", 'throttle:email-change'])->name('email.change');
        Route::post('email/confirm', ConfirmMyEmailChangeController::class)->middleware(["can:updateOwn,{$entities}", 'throttle:otp-verify'])->name('email.confirm');
    });

    Route::prefix('entities')->name('entities.')->group(function () use ($entities): void {
        Route::get('/', [EntityController::class, 'index'])->middleware("can:viewAny,{$entities}")->name('index');
        Route::post('export', ExportEntitiesController::class)->middleware(["can:export,{$entities}", 'throttle:exports'])->name('export');

        Route::whereNumber('entity')->group(function (): void {
            Route::get('{entity}', [EntityController::class, 'show'])->middleware('can:view,entity')->name('show');
            Route::put('{entity}', [EntityController::class, 'update'])->middleware(['can:update,entity', 'throttle:sensitive'])->name('update');
            Route::get('{entity}/profile-changes', EntityProfileChangeController::class)->middleware('can:viewProfileChanges,entity')->name('profile-changes');
            Route::post('{entity}/deactivate', DeactivateEntityController::class)->middleware(['can:deactivate,entity', 'throttle:sensitive'])->name('deactivate');
            Route::post('{entity}/reactivate', ReactivateEntityController::class)->middleware(['can:reactivate,entity', 'throttle:sensitive'])->name('reactivate');
            Route::post('{entity}/verification/resend', ResendEntityVerificationController::class)->middleware(['can:resendVerification,entity', 'throttle:sensitive'])->name('verification.resend');
            Route::get('{entity}/deletion-impact', EntityDeletionImpactController::class)->middleware('can:delete,entity')->name('deletion-impact');
            Route::delete('{entity}', SoftDeleteEntityController::class)->middleware(['can:delete,entity', 'throttle:sensitive'])->name('destroy');
            Route::post('{entity}/restore', RestoreEntityController::class)->middleware(['can:restore,entity', 'throttle:sensitive'])->name('restore');
            Route::delete('{entity}/permanent', PermanentlyDeleteEntityController::class)->middleware(['can:forceDelete,entity', 'throttle:sensitive'])->name('force-delete');
            Route::post('{entity}/export', ExportEntityPersonalDataController::class)->middleware(['can:exportPersonalData,entity', 'throttle:exports'])->name('personal-data.export');
        });
    });

    Route::prefix('admin/entity-email-changes')->name('entity-email-changes.')->group(function () use ($emailChanges): void {
        Route::get('/', [EntityEmailChangeController::class, 'index'])->middleware("can:viewAny,{$emailChanges}")->name('index');
        Route::post('{emailChange}/approve', [EntityEmailChangeController::class, 'approve'])->whereNumber('emailChange')->middleware(["can:decide,{$emailChanges}", 'throttle:sensitive'])->name('approve');
        Route::post('{emailChange}/reject', [EntityEmailChangeController::class, 'reject'])->whereNumber('emailChange')->middleware(["can:decide,{$emailChanges}", 'throttle:sensitive'])->name('reject');
    });

    Route::get('privacy/entities', PrivacyEntityLookupController::class)->middleware(["can:lookupEntities,{$privacyRequests}", 'throttle:search'])->name('privacy-entities.index');

    Route::prefix('privacy/requests')->name('privacy-requests.')->group(function () use ($privacyRequests): void {
        Route::get('/', [PrivacyRequestController::class, 'index'])->middleware("can:viewAny,{$privacyRequests}")->name('index');
        Route::post('/', [PrivacyRequestController::class, 'store'])->middleware(["can:create,{$privacyRequests}", 'throttle:sensitive'])->name('store');
        Route::get('{privacyRequest}', [PrivacyRequestController::class, 'show'])->whereNumber('privacyRequest')->middleware("can:view,{$privacyRequests}")->name('show');
        Route::post('{privacyRequest}/fulfil', [PrivacyRequestController::class, 'fulfil'])->whereNumber('privacyRequest')->middleware(["can:decide,{$privacyRequests}", 'throttle:sensitive'])->name('fulfil');
        Route::post('{privacyRequest}/reject', [PrivacyRequestController::class, 'reject'])->whereNumber('privacyRequest')->middleware(["can:decide,{$privacyRequests}", 'throttle:sensitive'])->name('reject');
    });

    Route::get('cities', [CityController::class, 'index'])->middleware("can:viewAny,{$cities}")->name('cities.index');
    Route::post('regions/{region}/cities', [CityController::class, 'store'])->whereNumber('region')->middleware(["can:create,{$cities}", 'throttle:sensitive'])->name('regions.cities.store');
    Route::put('cities/{city}', [CityController::class, 'update'])->whereNumber('city')->middleware(["can:update,{$cities}", 'throttle:sensitive'])->name('cities.update');
    Route::patch('cities/{city}/active', [CityController::class, 'toggle'])->whereNumber('city')->middleware(["can:update,{$cities}", 'throttle:sensitive'])->name('cities.active');
});
