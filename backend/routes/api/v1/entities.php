<?php

declare(strict_types=1);

use App\Enums\Entities\EntityDocument;
use App\Http\Controllers\Api\V1\Entities\ApproveEntityController;
use App\Http\Controllers\Api\V1\Entities\CancelMyEmailChangeController;
use App\Http\Controllers\Api\V1\Entities\ConfirmMyEmailChangeController;
use App\Http\Controllers\Api\V1\Entities\CurrentTermsController;
use App\Http\Controllers\Api\V1\Entities\DeactivateEntityController;
use App\Http\Controllers\Api\V1\Entities\EntityController;
use App\Http\Controllers\Api\V1\Entities\EntityDeletionImpactController;
use App\Http\Controllers\Api\V1\Entities\EntityDocumentDownloadController;
use App\Http\Controllers\Api\V1\Entities\EntityEmailChangeController;
use App\Http\Controllers\Api\V1\Entities\EntityProfileChangeController;
use App\Http\Controllers\Api\V1\Entities\EntityProfileChangeRequestController;
use App\Http\Controllers\Api\V1\Entities\EntityTimelineController;
use App\Http\Controllers\Api\V1\Entities\ExportEntitiesController;
use App\Http\Controllers\Api\V1\Entities\MyEmailChangeController;
use App\Http\Controllers\Api\V1\Entities\MyEntityController;
use App\Http\Controllers\Api\V1\Entities\MyEntityDocumentController;
use App\Http\Controllers\Api\V1\Entities\MyEntityLogoController;
use App\Http\Controllers\Api\V1\Entities\MyProfileChangeController;
use App\Http\Controllers\Api\V1\Entities\MyProfileChangeRequestController;
use App\Http\Controllers\Api\V1\Entities\MyProfileSectionController;
use App\Http\Controllers\Api\V1\Entities\MyVatCertificateController;
use App\Http\Controllers\Api\V1\Entities\PermanentlyDeleteEntityController;
use App\Http\Controllers\Api\V1\Entities\ReactivateEntityController;
use App\Http\Controllers\Api\V1\Entities\RegionController;
use App\Http\Controllers\Api\V1\Entities\RegisterEntityController;
use App\Http\Controllers\Api\V1\Entities\RegistrationEmailCodeController;
use App\Http\Controllers\Api\V1\Entities\RegistrationOptionsController;
use App\Http\Controllers\Api\V1\Entities\RejectEntityController;
use App\Http\Controllers\Api\V1\Entities\ResendEntityVerificationController;
use App\Http\Controllers\Api\V1\Entities\ResendRegistrationEmailCodeController;
use App\Http\Controllers\Api\V1\Entities\RestoreEntityController;
use App\Http\Controllers\Api\V1\Entities\SoftDeleteEntityController;
use App\Http\Controllers\Api\V1\Entities\VerifyRegistrationEmailCodeController;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\EmailChangeRequest;
use Illuminate\Support\Facades\Route;

$entities = Entity::class;
$emailChanges = EmailChangeRequest::class;
$profileChanges = EntityProfileChangeRequest::class;

Route::get('regions', [RegionController::class, 'index'])->middleware('throttle:public')->name('regions.index');
Route::get('terms/current', CurrentTermsController::class)->middleware('throttle:public')->name('terms.current');
Route::get('auth/register/options', RegistrationOptionsController::class)->middleware('throttle:public')->name('auth.register.options');
Route::post('auth/register', RegisterEntityController::class)->middleware('throttle:register')->name('auth.register');
Route::post('auth/register/email-code', RegistrationEmailCodeController::class)->middleware('throttle:registration-email-code')->name('auth.register.email-code');
Route::post('auth/register/email-code/verify', VerifyRegistrationEmailCodeController::class)->middleware('throttle:otp-verify')->name('auth.register.email-code.verify');
Route::post('auth/register/email-code/resend', ResendRegistrationEmailCodeController::class)->middleware('throttle:otp-resend')->name('auth.register.email-code.resend');

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current', 'nda.current'])->group(function () use ($entities, $emailChanges, $profileChanges): void {
    Route::prefix('entities/me')->name('entities.me.')->group(function () use ($entities): void {
        Route::get('/', [MyEntityController::class, 'show'])->middleware("can:viewOwn,{$entities}")->name('show');
        Route::prefix('profile')->name('profile.')->middleware(["can:updateOwn,{$entities}", 'throttle:sensitive'])->group(function (): void {
            Route::patch('identity', [MyProfileSectionController::class, 'identity'])->name('identity');
            Route::patch('classification', [MyProfileSectionController::class, 'classification'])->name('classification');
            Route::patch('governance', [MyProfileSectionController::class, 'governance'])->name('governance');
            Route::patch('contact', [MyProfileSectionController::class, 'contact'])->name('contact');
            Route::patch('billing', [MyProfileSectionController::class, 'billing'])->name('billing');
        });
        Route::post('logo', [MyEntityLogoController::class, 'store'])->middleware(["can:updateOwn,{$entities}", 'throttle:uploads'])->name('logo.store');
        Route::delete('logo', [MyEntityLogoController::class, 'destroy'])->middleware(["can:updateOwn,{$entities}", 'throttle:sensitive'])->name('logo.destroy');
        Route::post('vat-certificate', [MyVatCertificateController::class, 'store'])->middleware(["can:updateOwn,{$entities}", 'throttle:uploads'])->name('vat-certificate.store');
        Route::delete('vat-certificate', [MyVatCertificateController::class, 'destroy'])->middleware(["can:updateOwn,{$entities}", 'throttle:sensitive'])->name('vat-certificate.destroy');
        Route::post('vat-certificate/download', [MyVatCertificateController::class, 'download'])->middleware(["can:viewOwn,{$entities}", 'throttle:downloads'])->name('vat-certificate.download');
        Route::post('documents/{document}', [MyEntityDocumentController::class, 'store'])->whereIn('document', EntityDocument::requiredValues())->middleware(["can:updateOwn,{$entities}", 'throttle:uploads'])->name('documents.store');
        Route::post('documents/{document}/download', [MyEntityDocumentController::class, 'download'])->middleware(["can:viewOwn,{$entities}", 'throttle:downloads'])->name('documents.download');
        Route::get('profile-changes', MyProfileChangeController::class)->middleware("can:viewOwn,{$entities}")->name('profile-changes');
        Route::post('email/change', MyEmailChangeController::class)->middleware(["can:updateOwn,{$entities}", 'throttle:email-change'])->name('email.change');
        Route::post('email/confirm', ConfirmMyEmailChangeController::class)->middleware(["can:updateOwn,{$entities}", 'throttle:otp-verify'])->name('email.confirm');
        Route::get('profile-change', [MyProfileChangeRequestController::class, 'pending'])->middleware("can:viewOwn,{$entities}")->name('profile-change.show');
        Route::post('profile-change/cancel', [MyProfileChangeRequestController::class, 'cancel'])->middleware(["can:updateOwn,{$entities}", 'throttle:sensitive'])->name('profile-change.cancel');
        Route::get('profile-change-requests', [MyProfileChangeRequestController::class, 'index'])->middleware("can:viewOwn,{$entities}")->name('profile-change-requests');
        Route::post('email/cancel', CancelMyEmailChangeController::class)->middleware(["can:updateOwn,{$entities}", 'throttle:sensitive'])->name('email.cancel');
    });

    Route::prefix('entities')->name('entities.')->group(function () use ($entities): void {
        Route::get('/', [EntityController::class, 'index'])->middleware("can:viewAny,{$entities}")->name('index');
        Route::post('export', ExportEntitiesController::class)->middleware(["can:export,{$entities}", 'throttle:exports'])->name('export');

        Route::whereNumber('entity')->group(function (): void {
            Route::get('{entity}', [EntityController::class, 'show'])->middleware('can:view,entity')->name('show');
            Route::put('{entity}', [EntityController::class, 'update'])->middleware(['can:update,entity', 'throttle:sensitive'])->name('update');
            Route::get('{entity}/profile-changes', EntityProfileChangeController::class)->middleware('can:viewProfileChanges,entity')->name('profile-changes');
            Route::get('{entity}/timeline', EntityTimelineController::class)->middleware('can:view,entity')->name('timeline');
            Route::post('{entity}/documents/{document}/download', EntityDocumentDownloadController::class)->middleware(['can:viewDocuments,entity', 'throttle:downloads'])->name('documents.download');
            Route::post('{entity}/deactivate', DeactivateEntityController::class)->middleware(['can:deactivate,entity', 'throttle:sensitive'])->name('deactivate');
            Route::post('{entity}/reactivate', ReactivateEntityController::class)->middleware(['can:reactivate,entity', 'throttle:sensitive'])->name('reactivate');
            Route::post('{entity}/approve', ApproveEntityController::class)->middleware(['can:review,entity', 'throttle:sensitive'])->name('approve');
            Route::post('{entity}/reject', RejectEntityController::class)->middleware(['can:review,entity', 'throttle:sensitive'])->name('reject');
            Route::post('{entity}/verification/resend', ResendEntityVerificationController::class)->middleware(['can:resendVerification,entity', 'throttle:sensitive'])->name('verification.resend');
            Route::get('{entity}/deletion-impact', EntityDeletionImpactController::class)->middleware('can:delete,entity')->name('deletion-impact');
            Route::delete('{entity}', SoftDeleteEntityController::class)->middleware(['can:delete,entity', 'throttle:sensitive'])->name('destroy');
            Route::post('{entity}/restore', RestoreEntityController::class)->middleware(['can:restore,entity', 'throttle:sensitive'])->name('restore');
            Route::delete('{entity}/permanent', PermanentlyDeleteEntityController::class)->middleware(['can:forceDelete,entity', 'throttle:sensitive'])->name('force-delete');
        });
    });

    Route::prefix('admin/entity-profile-changes')->name('entity-profile-changes.')->group(function () use ($profileChanges): void {
        Route::get('/', [EntityProfileChangeRequestController::class, 'index'])->middleware("can:viewAny,{$profileChanges}")->name('index');
        Route::get('{profileChange}', [EntityProfileChangeRequestController::class, 'show'])->whereUuid('profileChange')->middleware('can:view,profileChange')->name('show');
        Route::post('{profileChange}/decide', [EntityProfileChangeRequestController::class, 'decide'])->whereUuid('profileChange')->middleware(['can:decide,profileChange', 'throttle:sensitive'])->name('decide');
    });

    Route::prefix('admin/entity-email-changes')->name('entity-email-changes.')->group(function () use ($emailChanges): void {
        Route::get('/', [EntityEmailChangeController::class, 'index'])->middleware("can:viewAny,{$emailChanges}")->name('index');
        Route::post('{emailChange}/approve', [EntityEmailChangeController::class, 'approve'])->whereNumber('emailChange')->middleware(["can:decide,{$emailChanges}", 'throttle:sensitive'])->name('approve');
        Route::post('{emailChange}/reject', [EntityEmailChangeController::class, 'reject'])->whereNumber('emailChange')->middleware(["can:decide,{$emailChanges}", 'throttle:sensitive'])->name('reject');
    });
});
