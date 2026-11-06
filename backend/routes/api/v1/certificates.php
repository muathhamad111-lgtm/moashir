<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Certificates\AccreditedCertificateController;
use App\Http\Controllers\Api\V1\Certificates\CertificateController;
use App\Http\Controllers\Api\V1\Certificates\CertificateLevelsController;
use App\Http\Controllers\Api\V1\Certificates\CertificateLifecycleController;
use App\Http\Controllers\Api\V1\Certificates\CertificateVerificationController;
use App\Http\Controllers\Api\V1\Reports\FeedbackReportController;
use App\Http\Middleware\EnsureJsonRequest;
use App\Http\Middleware\ValidateDownloadSignature;
use App\Models\Certificates\Certificate;
use Illuminate\Support\Facades\Route;

$signedIn = ['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'];
$viewReport = 'can:'.Ability::ViewFeedbackReport->value.',assessment';

Route::get('verify/{code}', CertificateVerificationController::class)
    ->where('code', '[0-9A-Za-z\-]{1,60}')
    ->middleware('throttle:certificate-verify')
    ->name('certificates.verify');

Route::get('content/certificate-levels', CertificateLevelsController::class)->middleware('throttle:public')->name('content.certificate-levels');

Route::middleware($signedIn)->group(function () use ($viewReport): void {
    Route::prefix('certificates')->name('certificates.')->group(function (): void {
        Route::get('/', [CertificateController::class, 'index'])->middleware('can:viewAny,'.Certificate::class)->name('index');

        Route::whereUuid('certificate')->group(function (): void {
            Route::get('{certificate}', [CertificateController::class, 'show'])->middleware('can:view,certificate')->name('show');
            Route::post('{certificate}/download', [CertificateController::class, 'download'])->middleware(['can:download,certificate', 'throttle:downloads'])->name('download');
            Route::post('{certificate}/revoke', [CertificateLifecycleController::class, 'revoke'])->middleware(['can:revoke,certificate', 'throttle:sensitive'])->name('revoke');
            Route::post('{certificate}/reissue', [CertificateLifecycleController::class, 'reissue'])->middleware(['can:reissue,certificate', 'throttle:sensitive'])->name('reissue');
        });
    });

    Route::prefix('assessments')->name('assessments.')->whereNumber('assessment')->group(function () use ($viewReport): void {
        Route::post('{assessment}/certificate', [AccreditedCertificateController::class, 'store'])
            ->middleware(['can:'.Ability::IssueAccreditedCertificate->value.',assessment', 'throttle:sensitive'])
            ->name('certificate.store');
        Route::get('{assessment}/feedback-report', [FeedbackReportController::class, 'show'])->middleware($viewReport)->name('feedback-report.show');
        Route::post('{assessment}/feedback-report/export', [FeedbackReportController::class, 'export'])->middleware([$viewReport, 'throttle:exports'])->name('feedback-report.export');
        Route::get('{assessment}/feedback-report/pdf', [FeedbackReportController::class, 'pdf'])
            ->withoutMiddleware([EnsureJsonRequest::class])
            ->middleware([ValidateDownloadSignature::class, $viewReport, 'throttle:downloads'])
            ->name('feedback-report.pdf');
    });
});
