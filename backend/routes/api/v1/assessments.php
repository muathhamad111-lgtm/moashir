<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Assessments\AccreditedAssessmentController;
use App\Http\Controllers\Api\V1\Assessments\AdvancedAssessmentController;
use App\Http\Controllers\Api\V1\Assessments\AssessmentController;
use App\Http\Controllers\Api\V1\Assessments\AssessmentFormController;
use App\Http\Controllers\Api\V1\Assessments\AssessmentOverrideController;
use App\Http\Controllers\Api\V1\Assessments\AssessmentResultController;
use App\Http\Controllers\Api\V1\Assessments\AssessorReviewController;
use App\Http\Controllers\Api\V1\Assessments\EvidenceBundleController;
use App\Http\Controllers\Api\V1\Assessments\EvidenceController;
use App\Http\Controllers\Api\V1\Assessments\SelfAssessmentController;
use App\Http\Controllers\Api\V1\Assessments\SubmitAssessmentController;
use App\Models\Assessments\Assessment;
use Illuminate\Support\Facades\Route;

$assessments = Assessment::class;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])
    ->prefix('assessments')
    ->name('assessments.')
    ->group(function () use ($assessments): void {
        Route::get('/', [AssessmentController::class, 'index'])->middleware("can:viewAny,{$assessments}")->name('index');
        Route::post('/', [SelfAssessmentController::class, 'store'])->middleware(["can:createSelf,{$assessments}", 'throttle:sensitive'])->name('store');
        Route::get('accredited/current', [AccreditedAssessmentController::class, 'current'])->middleware("can:viewAccreditedCurrent,{$assessments}")->name('accredited.current');
        Route::get('advanced/current', [AdvancedAssessmentController::class, 'current'])->middleware("can:viewAdvancedCurrent,{$assessments}")->name('advanced.current');
        Route::post('advanced', [AdvancedAssessmentController::class, 'apply'])->middleware(["can:applyAdvanced,{$assessments}", 'throttle:sensitive'])->name('advanced.apply');

        Route::whereNumber('assessment')->whereUuid(['evidence', 'bundle'])->group(function (): void {
            Route::get('{assessment}', [AssessmentController::class, 'show'])->middleware('can:view,assessment')->name('show');
            Route::put('{assessment}', [SelfAssessmentController::class, 'update'])->middleware(['can:update,assessment', 'throttle:assessment-write'])->name('update');
            Route::delete('{assessment}', [AdvancedAssessmentController::class, 'destroy'])->middleware(['can:deleteDraft,assessment', 'throttle:sensitive'])->name('destroy');
            Route::get('{assessment}/form', AssessmentFormController::class)->middleware('can:view,assessment')->name('form');
            Route::post('{assessment}/submit', SubmitAssessmentController::class)->middleware(['can:submit,assessment', 'throttle:sensitive'])->name('submit');
            Route::get('{assessment}/result', AssessmentResultController::class)->middleware('can:viewResult,assessment')->name('result');
            Route::put('{assessment}/cv', [AccreditedAssessmentController::class, 'saveCv'])->middleware(['can:saveCv,assessment', 'throttle:assessment-write'])->name('cv.update');

            Route::get('{assessment}/evidence', [EvidenceController::class, 'index'])->middleware('can:viewEvidence,assessment')->name('evidence.index');
            Route::post('{assessment}/evidence', [EvidenceController::class, 'store'])->middleware(['can:manageEvidence,assessment', 'throttle:uploads'])->name('evidence.store');
            Route::delete('{assessment}/evidence/{evidence}', [EvidenceController::class, 'destroy'])->middleware(['can:removeEvidence,assessment,evidence', 'throttle:assessment-write'])->name('evidence.destroy');
            Route::post('{assessment}/evidence/{evidence}/download', [EvidenceController::class, 'download'])->middleware(['can:downloadEvidence,assessment,evidence', 'throttle:downloads'])->name('evidence.download');
            Route::post('{assessment}/evidence/bundles', [EvidenceBundleController::class, 'store'])->middleware(['can:requestEvidenceBundle,assessment', 'throttle:exports'])->name('evidence.bundles.store');
            Route::get('{assessment}/evidence/bundles/{bundle}', [EvidenceBundleController::class, 'show'])->middleware('can:viewEvidenceBundle,assessment,bundle')->name('evidence.bundles.show');
            Route::post('{assessment}/evidence/bundles/{bundle}/download', [EvidenceBundleController::class, 'download'])->middleware(['can:viewEvidenceBundle,assessment,bundle', 'throttle:downloads'])->name('evidence.bundles.download');

            Route::get('{assessment}/review', [AssessorReviewController::class, 'show'])->middleware('can:review,assessment')->name('review.show');
            Route::post('{assessment}/start-review', [AssessorReviewController::class, 'start'])->middleware(['can:score,assessment', 'throttle:sensitive'])->name('review.start');
            Route::put('{assessment}/review/scores', [AssessorReviewController::class, 'scores'])->middleware(['can:score,assessment', 'throttle:assessment-write'])->name('review.scores');
            Route::put('{assessment}/review/feedback', [AssessorReviewController::class, 'feedback'])->middleware(['can:score,assessment', 'throttle:assessment-write'])->name('review.feedback');
            Route::put('{assessment}/review/summary', [AssessorReviewController::class, 'summary'])->middleware(['can:score,assessment', 'throttle:assessment-write'])->name('review.summary');
            Route::post('{assessment}/request-completion', [AssessorReviewController::class, 'requestCompletion'])->middleware(['can:score,assessment', 'throttle:sensitive'])->name('review.request-completion');
            Route::post('{assessment}/complete', [AssessorReviewController::class, 'complete'])->middleware(['can:score,assessment', 'throttle:sensitive'])->name('review.complete');

            Route::post('{assessment}/scores/override', [AssessmentOverrideController::class, 'scores'])->middleware(['can:override,assessment', 'throttle:sensitive'])->name('overrides.scores');
            Route::post('{assessment}/reopen', [AssessmentOverrideController::class, 'reopen'])->middleware(['can:override,assessment', 'throttle:sensitive'])->name('overrides.reopen');
            Route::post('{assessment}/transition/force', [AssessmentOverrideController::class, 'force'])->middleware(['can:override,assessment', 'throttle:sensitive'])->name('overrides.force');
        });
    });
