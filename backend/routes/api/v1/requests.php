<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Assessments\AdvancedRequestController;
use App\Http\Controllers\Api\V1\Assessments\AssignedRequestController;
use App\Http\Controllers\Api\V1\Assessments\RequestAssignmentController;
use App\Http\Controllers\Api\V1\Assessments\RequestQueueController;
use App\Models\Assessments\Assessment;
use Illuminate\Support\Facades\Route;

$assessments = Assessment::class;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])
    ->prefix('requests')
    ->name('requests.')
    ->group(function () use ($assessments): void {
        Route::get('/', [RequestQueueController::class, 'index'])->middleware("can:viewRequestQueue,{$assessments}")->name('index');
        Route::get('assigned', AssignedRequestController::class)->middleware("can:viewAssignedRequests,{$assessments}")->name('assigned');
        Route::get('assessors', [RequestAssignmentController::class, 'assessors'])->middleware("can:listAssessors,{$assessments}")->name('assessors');

        Route::whereNumber('assessment')->group(function (): void {
            Route::get('{assessment}', [RequestQueueController::class, 'show'])->middleware('can:viewRequest,assessment')->name('show');
            Route::post('{assessment}/assign', [RequestAssignmentController::class, 'assign'])->middleware(['can:assign,assessment', 'throttle:sensitive'])->name('assign');
            Route::post('{assessment}/reassign', [RequestAssignmentController::class, 'reassign'])->middleware(['can:reassign,assessment', 'throttle:sensitive'])->name('reassign');
            Route::post('{assessment}/return-for-completion', [AdvancedRequestController::class, 'returnForCompletion'])->middleware(['can:returnForCompletion,assessment', 'throttle:sensitive'])->name('return-for-completion');
            Route::post('{assessment}/withdraw', [AdvancedRequestController::class, 'withdraw'])->middleware(['can:withdraw,assessment', 'throttle:sensitive'])->name('withdraw');
        });
    });
