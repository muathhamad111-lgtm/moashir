<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Criteria\CheckCriteriaVersionController;
use App\Http\Controllers\Api\V1\Criteria\CorrectCriteriaTextController;
use App\Http\Controllers\Api\V1\Criteria\CriteriaVersionController;
use App\Http\Controllers\Api\V1\Criteria\GuidingPointActivationController;
use App\Http\Controllers\Api\V1\Criteria\GuidingPointController;
use App\Http\Controllers\Api\V1\Criteria\MainCriterionActivationController;
use App\Http\Controllers\Api\V1\Criteria\MainCriterionController;
use App\Http\Controllers\Api\V1\Criteria\PublicModelCriteriaController;
use App\Http\Controllers\Api\V1\Criteria\PublishCriteriaVersionController;
use App\Http\Controllers\Api\V1\Criteria\PublishedCriteriaController;
use App\Http\Controllers\Api\V1\Criteria\ReorderGuidingPointsController;
use App\Http\Controllers\Api\V1\Criteria\ReorderMainCriteriaController;
use App\Http\Controllers\Api\V1\Criteria\ReorderSubCriteriaController;
use App\Http\Controllers\Api\V1\Criteria\SubCriterionActivationController;
use App\Http\Controllers\Api\V1\Criteria\SubCriterionController;
use App\Http\Controllers\Api\V1\Criteria\SubCriterionEvaluationTypeController;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Route;

$versions = CriteriaVersion::class;

Route::get('content/model-criteria', PublicModelCriteriaController::class)->middleware('throttle:public')->name('content.model-criteria');

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->group(function () use ($versions): void {
    Route::get('assessments/criteria', PublishedCriteriaController::class)->middleware("can:viewPublished,{$versions}")->name('assessments.criteria');

    Route::prefix('criteria')->name('criteria.')->group(function () use ($versions): void {
        Route::get('versions', [CriteriaVersionController::class, 'index'])->middleware("can:viewAny,{$versions}")->name('versions.index');
        Route::post('versions', [CriteriaVersionController::class, 'store'])->middleware(["can:create,{$versions}", 'throttle:criteria-write'])->name('versions.store');
        Route::get('versions/{version}', [CriteriaVersionController::class, 'show'])->middleware('can:view,version')->name('versions.show');
        Route::delete('versions/{version}', [CriteriaVersionController::class, 'destroy'])->middleware(['can:discard,version', 'throttle:criteria-write'])->name('versions.destroy');
        Route::get('versions/{version}/check', CheckCriteriaVersionController::class)->middleware('can:view,version')->name('versions.check');
        Route::post('versions/{version}/publish', PublishCriteriaVersionController::class)->middleware(['can:publish,version', 'throttle:sensitive'])->name('versions.publish');
        Route::patch('versions/{version}/labels', CorrectCriteriaTextController::class)->middleware(['can:correctText,version', 'throttle:sensitive'])->name('versions.labels');

        Route::get('mains', [MainCriterionController::class, 'index'])->middleware("can:viewAny,{$versions}")->name('mains.index');
        Route::post('mains', [MainCriterionController::class, 'store'])->middleware(["can:manageTree,{$versions}", 'throttle:criteria-write'])->name('mains.store');
        Route::post('mains/reorder', ReorderMainCriteriaController::class)->middleware(["can:manageTree,{$versions}", 'throttle:criteria-write'])->name('mains.reorder');
        Route::get('mains/{main}', [MainCriterionController::class, 'show'])->middleware('can:view,main')->name('mains.show');
        Route::put('mains/{main}', [MainCriterionController::class, 'update'])->middleware(['can:update,main', 'throttle:criteria-write'])->name('mains.update');
        Route::patch('mains/{main}/active', MainCriterionActivationController::class)->middleware(['can:update,main', 'throttle:criteria-write'])->name('mains.active');
        Route::delete('mains/{main}', [MainCriterionController::class, 'destroy'])->middleware(['can:delete,main', 'throttle:criteria-write'])->name('mains.destroy');
        Route::post('mains/{main}/subs/reorder', ReorderSubCriteriaController::class)->middleware(['can:update,main', 'throttle:criteria-write'])->name('mains.subs.reorder');

        Route::post('subs', [SubCriterionController::class, 'store'])->middleware(["can:manageTree,{$versions}", 'throttle:criteria-write'])->name('subs.store');
        Route::get('subs/{sub}', [SubCriterionController::class, 'show'])->middleware('can:view,sub')->name('subs.show');
        Route::put('subs/{sub}', [SubCriterionController::class, 'update'])->middleware(['can:update,sub', 'throttle:criteria-write'])->name('subs.update');
        Route::patch('subs/{sub}/active', SubCriterionActivationController::class)->middleware(['can:update,sub', 'throttle:criteria-write'])->name('subs.active');
        Route::patch('subs/{sub}/evaluation-type', SubCriterionEvaluationTypeController::class)->middleware(['can:update,sub', 'throttle:criteria-write'])->name('subs.evaluation-type');
        Route::delete('subs/{sub}', [SubCriterionController::class, 'destroy'])->middleware(['can:delete,sub', 'throttle:criteria-write'])->name('subs.destroy');
        Route::post('subs/{sub}/points', [GuidingPointController::class, 'store'])->middleware(['can:update,sub', 'throttle:criteria-write'])->name('subs.points.store');
        Route::post('subs/{sub}/points/reorder', ReorderGuidingPointsController::class)->middleware(['can:update,sub', 'throttle:criteria-write'])->name('subs.points.reorder');

        Route::put('points/{point}', [GuidingPointController::class, 'update'])->middleware(['can:update,point', 'throttle:criteria-write'])->name('points.update');
        Route::patch('points/{point}/active', GuidingPointActivationController::class)->middleware(['can:update,point', 'throttle:criteria-write'])->name('points.active');
        Route::delete('points/{point}', [GuidingPointController::class, 'destroy'])->middleware(['can:delete,point', 'throttle:criteria-write'])->name('points.destroy');
    });
});
