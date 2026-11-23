<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Enums\Insights\MaturityBand;
use App\Http\Controllers\Api\V1\Insights\AssessmentInsightsController;
use App\Http\Controllers\Api\V1\Insights\BoardReportController;
use App\Http\Controllers\Api\V1\Insights\EntityInsightsController;
use App\Http\Controllers\Api\V1\Insights\MaturityTextController;
use App\Http\Controllers\Api\V1\Insights\MyInsightsController;
use App\Http\Middleware\EnsureInsightsKitActive;
use App\Http\Middleware\EnsureJsonRequest;
use App\Http\Middleware\ValidateDownloadSignature;
use App\Models\Insights\MaturityInterpretation;
use Illuminate\Support\Facades\Route;

$signedIn = ['auth:sanctum', 'session.timeouts', 'mfa', 'password.current', 'nda.current'];
$viewOwn = 'can:'.Ability::ViewOwnInsights->value;
$viewEntity = 'can:'.Ability::ViewEntityInsights->value.',entity';
$viewResult = 'can:'.Ability::ViewAssessmentInsights->value.',assessment';
$manageTexts = 'can:manage,'.MaturityInterpretation::class;

Route::middleware($signedIn)->group(function () use ($viewOwn, $viewEntity, $viewResult, $manageTexts): void {
    Route::prefix('entities/me')->name('entities.me.')->middleware([$viewOwn, EnsureInsightsKitActive::class])->group(function (): void {
        Route::get('insights', [MyInsightsController::class, 'overview'])->name('insights.show');
        Route::get('insights/results', [MyInsightsController::class, 'results'])->name('insights.results');
        Route::get('report-library', [MyInsightsController::class, 'library'])->name('report-library');
    });

    Route::prefix('entities')->name('entities.')->whereNumber('entity')->middleware($viewEntity)->group(function (): void {
        Route::get('{entity}/insights', [EntityInsightsController::class, 'overview'])->name('insights.show');
        Route::get('{entity}/insights/results', [EntityInsightsController::class, 'results'])->name('insights.results');
        Route::get('{entity}/report-library', [EntityInsightsController::class, 'library'])->name('report-library');
    });

    Route::prefix('assessments')->name('assessments.')->whereNumber('assessment')->group(function () use ($viewResult): void {
        // Authorization first (404 for a stranger), then the entity's Kit gate.
        $viewUnlocked = [$viewResult, EnsureInsightsKitActive::class];
        Route::get('{assessment}/insights/strengths-gaps', [AssessmentInsightsController::class, 'strengthsGaps'])->middleware($viewUnlocked)->name('insights.strengths-gaps');
        Route::get('{assessment}/insights/maturity', [AssessmentInsightsController::class, 'maturity'])->middleware($viewUnlocked)->name('insights.maturity');
        Route::get('{assessment}/insights/executive-summary', [AssessmentInsightsController::class, 'executiveSummary'])->middleware($viewUnlocked)->name('insights.executive-summary');
        Route::post('{assessment}/board-report/export', [BoardReportController::class, 'export'])->middleware([...$viewUnlocked, 'throttle:exports'])->name('board-report.export');
        Route::get('{assessment}/board-report/pdf', [BoardReportController::class, 'pdf'])
            ->withoutMiddleware([EnsureJsonRequest::class])
            ->middleware([ValidateDownloadSignature::class, ...$viewUnlocked, 'throttle:downloads'])
            ->name('board-report.pdf');
    });

    Route::prefix('content/maturity-texts')->name('content.maturity-texts.')->middleware($manageTexts)->group(function (): void {
        Route::get('/', [MaturityTextController::class, 'index'])->name('index');
        Route::put('{band}', [MaturityTextController::class, 'update'])->whereIn('band', MaturityBand::values())->middleware('throttle:content-write')->name('update');
        Route::delete('{maturityText}', [MaturityTextController::class, 'destroy'])->whereNumber('maturityText')->middleware('throttle:content-write')->name('destroy');
    });
});
