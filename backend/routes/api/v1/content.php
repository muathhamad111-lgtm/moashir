<?php

declare(strict_types=1);

use App\Enums\Identity\TermsKind;
use App\Http\Controllers\Api\V1\Cms\CmsPageController;
use App\Http\Controllers\Api\V1\Cms\CmsPagePublicationController;
use App\Http\Controllers\Api\V1\Cms\CmsSectionController;
use App\Http\Controllers\Api\V1\Cms\CmsSectionItemController;
use App\Http\Controllers\Api\V1\Cms\ContentImageController;
use App\Http\Controllers\Api\V1\Cms\LegalVersionController;
use App\Http\Controllers\Api\V1\Cms\PublicContentController;
use App\Http\Controllers\Api\V1\Cms\PublishLegalVersionController;
use App\Http\Controllers\Api\V1\Cms\ReorderCmsSectionItemsController;
use App\Http\Controllers\Api\V1\Cms\ReorderCmsSectionsController;
use App\Http\Controllers\Api\V1\Cms\SiteSettingsController;
use App\Http\Controllers\Api\V1\Cms\ToggleCmsSectionController;
use App\Http\Controllers\Api\V1\Cms\ToggleCmsSectionItemController;
use App\Http\Controllers\Api\V1\Files\PublicImageController;
use App\Http\Middleware\EnsureJsonRequest;
use App\Models\Cms\CmsPage;
use App\Models\Identity\TermsVersion;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

$manage = 'can:manage,'.CmsPage::class;
$manageLegal = 'can:manage,'.TermsVersion::class;
$signedIn = ['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'];

Route::prefix('content')->name('content.')->middleware('throttle:public')->group(function (): void {
    Route::get('status', [PublicContentController::class, 'status'])->name('status');
    Route::get('home', [PublicContentController::class, 'home'])->name('home');
    Route::get('landing', [PublicContentController::class, 'landing'])->name('landing');
    Route::get('pages/{page}', [PublicContentController::class, 'page'])->name('pages.show');
    Route::get('legal/{kind}', [PublicContentController::class, 'legal'])->whereIn('kind', TermsKind::cases())->name('legal.show');
});

Route::get('content/files/{asset}', PublicImageController::class)
    ->whereUuid('asset')
    ->withoutMiddleware([EnsureJsonRequest::class, EnsureFrontendRequestsAreStateful::class])
    ->middleware('throttle:public-assets')
    ->name('content.files.show');

Route::middleware([...$signedIn, $manage])
    ->prefix('admin/content')
    ->name('admin.content.')
    ->group(function (): void {
        Route::get('home', [CmsPageController::class, 'home'])->name('home');
        Route::get('pages', [CmsPageController::class, 'index'])->name('pages.index');
        Route::get('pages/{cmsPage}', [CmsPageController::class, 'show'])->name('pages.show');
        Route::put('pages/{cmsPage}', [CmsPageController::class, 'update'])->middleware('throttle:content-write')->name('pages.update');
        Route::post('pages/{cmsPage}/publish', [CmsPagePublicationController::class, 'publish'])->middleware('throttle:sensitive')->name('pages.publish');
        Route::post('pages/{cmsPage}/unpublish', [CmsPagePublicationController::class, 'unpublish'])->middleware('throttle:sensitive')->name('pages.unpublish');
        Route::post('pages/{cmsPage}/sections', [CmsSectionController::class, 'store'])->middleware('throttle:content-write')->name('sections.store');

        Route::patch('sections/reorder', ReorderCmsSectionsController::class)->middleware('throttle:content-write')->name('sections.reorder');

        Route::whereNumber(['section', 'item'])->middleware('throttle:content-write')->group(function (): void {
            Route::patch('sections/{section}', [CmsSectionController::class, 'update'])->name('sections.update');
            Route::patch('sections/{section}/enabled', ToggleCmsSectionController::class)->name('sections.enabled');
            Route::delete('sections/{section}', [CmsSectionController::class, 'destroy'])->name('sections.destroy');
            Route::post('sections/{section}/items', [CmsSectionItemController::class, 'store'])->name('items.store');
            Route::patch('sections/{section}/items/reorder', ReorderCmsSectionItemsController::class)->name('items.reorder');

            Route::scopeBindings()->group(function (): void {
                Route::put('sections/{section}/items/{item}', [CmsSectionItemController::class, 'update'])->name('items.update');
                Route::patch('sections/{section}/items/{item}/enabled', ToggleCmsSectionItemController::class)->name('items.enabled');
                Route::delete('sections/{section}/items/{item}', [CmsSectionItemController::class, 'destroy'])->name('items.destroy');
            });
        });

        Route::get('site-settings', [SiteSettingsController::class, 'show'])->name('site-settings.show');
        Route::patch('site-settings', [SiteSettingsController::class, 'update'])->middleware('throttle:content-write')->name('site-settings.update');
        Route::post('images', ContentImageController::class)->middleware('throttle:uploads')->name('images.store');
    });

Route::middleware([...$signedIn, $manageLegal])
    ->prefix('admin/content/legal')
    ->name('admin.content.legal.')
    ->group(function (): void {
        Route::whereIn('kind', TermsKind::cases())->group(function (): void {
            Route::get('{kind}/versions', [LegalVersionController::class, 'index'])->name('versions.index');
            Route::post('{kind}/versions', [LegalVersionController::class, 'store'])->middleware('throttle:content-write')->name('versions.store');
        });

        Route::whereNumber('termsVersion')->group(function (): void {
            Route::get('versions/{termsVersion}', [LegalVersionController::class, 'show'])->name('versions.show');
            Route::put('versions/{termsVersion}', [LegalVersionController::class, 'update'])->middleware('throttle:content-write')->name('versions.update');
            Route::delete('versions/{termsVersion}', [LegalVersionController::class, 'destroy'])->middleware('throttle:content-write')->name('versions.destroy');
            Route::post('versions/{termsVersion}/publish', PublishLegalVersionController::class)->middleware('throttle:sensitive')->name('versions.publish');
        });
    });
