<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Award\AwardCycleController;
use App\Http\Controllers\Api\V1\Award\AwardResultsController;
use App\Http\Controllers\Api\V1\Award\CommitteeMembershipController;
use App\Http\Controllers\Api\V1\Award\CommitteeRosterController;
use App\Http\Controllers\Api\V1\Award\CycleCandidatesController;
use App\Http\Controllers\Api\V1\Award\CycleDefaultsController;
use App\Http\Controllers\Api\V1\Award\CycleStaffController;
use App\Http\Controllers\Api\V1\Award\CycleTransitionController;
use App\Http\Controllers\Api\V1\Award\FilteringController;
use App\Http\Controllers\Api\V1\Award\FilteringDecisionController;
use App\Http\Controllers\Api\V1\Award\MyCommitteeEvaluationController;
use App\Http\Controllers\Api\V1\Award\MyCommitteeWorkController;
use App\Http\Controllers\Api\V1\Award\PublicCycleController;
use App\Http\Controllers\Api\V1\Award\SecretaryDecisionController;
use App\Http\Controllers\Api\V1\Award\SecretaryDeskController;
use App\Http\Controllers\Api\V1\Award\SecretaryDiscussionController;
use App\Models\Award\AwardCycle;
use Illuminate\Support\Facades\Route;

$cycles = AwardCycle::class;

Route::get('award/cycles/current', PublicCycleController::class)->middleware('throttle:public')->name('award.cycles.current');

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])
    ->prefix('award/cycles')
    ->name('award.cycles.')
    ->group(function () use ($cycles): void {
        Route::get('/', [AwardCycleController::class, 'index'])->middleware("can:viewAny,{$cycles}")->name('index');
        Route::post('/', [AwardCycleController::class, 'store'])->middleware(["can:create,{$cycles}", 'throttle:sensitive'])->name('store');
        Route::get('default-conditions', CycleDefaultsController::class)->middleware("can:create,{$cycles}")->name('defaults');
        Route::get('candidates', CycleCandidatesController::class)->middleware(["can:listCandidates,{$cycles}", 'throttle:search'])->name('candidates');

        Route::whereNumber(['cycle', 'assessment'])->group(function (): void {
            Route::get('{cycle}', [AwardCycleController::class, 'show'])->middleware('can:view,cycle')->name('show');
            Route::put('{cycle}', [AwardCycleController::class, 'update'])->middleware(['can:manage,cycle', 'throttle:sensitive'])->name('update');
            Route::put('{cycle}/members', [CycleStaffController::class, 'members'])->middleware(['can:manage,cycle', 'throttle:sensitive'])->name('members');
            Route::put('{cycle}/initial-assessors', [CycleStaffController::class, 'initialAssessors'])->middleware(['can:manage,cycle', 'throttle:sensitive'])->name('initial-assessors');
            Route::post('{cycle}/open', [CycleTransitionController::class, 'open'])->middleware(['can:manage,cycle', 'throttle:sensitive'])->name('open');
            Route::post('{cycle}/close-submissions', [CycleTransitionController::class, 'closeSubmissions'])->middleware(['can:manage,cycle', 'throttle:sensitive'])->name('close-submissions');
            Route::post('{cycle}/close', [CycleTransitionController::class, 'close'])->middleware(['can:manage,cycle', 'throttle:sensitive'])->name('close');
            Route::post('{cycle}/reopen', [CycleTransitionController::class, 'reopen'])->middleware(['can:reopen,cycle', 'throttle:sensitive'])->name('reopen');

            Route::get('{cycle}/filtering', [FilteringController::class, 'index'])->middleware('can:viewFiltering,cycle')->name('filtering.index');
            Route::get('{cycle}/filtering/{assessment}', [FilteringController::class, 'show'])->middleware('can:viewFilteringRequest,cycle,assessment')->name('filtering.show');
            Route::post('{cycle}/filtering/{assessment}/qualify', [FilteringDecisionController::class, 'qualify'])->middleware(['can:decideFiltering,cycle,assessment', 'throttle:sensitive'])->name('filtering.qualify');
            Route::post('{cycle}/filtering/{assessment}/disqualify', [FilteringDecisionController::class, 'disqualify'])->middleware(['can:decideFiltering,cycle,assessment', 'throttle:sensitive'])->name('filtering.disqualify');

            Route::post('{cycle}/start-committee', [CycleTransitionController::class, 'startCommittee'])->middleware(['can:manage,cycle', 'throttle:sensitive'])->name('start-committee');
            Route::post('{cycle}/announce', [CycleTransitionController::class, 'announce'])->middleware(['can:'.Ability::AnnounceAwardResults->value.',cycle', 'throttle:sensitive'])->name('announce');
        });

        Route::whereNumber(['cycle', 'member'])->prefix('{cycle}/committee')->name('committee.')->group(function (): void {
            Route::get('/', [CommitteeRosterController::class, 'index'])->middleware('can:'.Ability::ViewCommitteeRoster->value.',cycle')->name('index');
            Route::get('candidates', [CommitteeRosterController::class, 'candidates'])->middleware(['can:'.Ability::ManageCommitteeRoster->value.',cycle', 'throttle:search'])->name('candidates');
            Route::post('members', [CommitteeRosterController::class, 'store'])->middleware(['can:'.Ability::ManageCommitteeRoster->value.',cycle', 'throttle:sensitive'])->name('members.store');
            Route::delete('members/{member}', [CommitteeRosterController::class, 'destroy'])->middleware(['can:'.Ability::ManageCommitteeMember->value.',cycle,member', 'throttle:sensitive'])->name('members.destroy');
            Route::post('members/{member}/disable', [CommitteeMembershipController::class, 'disable'])->middleware(['can:'.Ability::ManageCommitteeMember->value.',cycle,member', 'throttle:sensitive'])->name('members.disable');
            Route::post('members/{member}/exclude', [CommitteeMembershipController::class, 'exclude'])->middleware(['can:'.Ability::ManageCommitteeMember->value.',cycle,member', 'throttle:sensitive'])->name('members.exclude');
            Route::post('members/{member}/substitute', [CommitteeMembershipController::class, 'substitute'])->middleware(['can:'.Ability::ManageCommitteeMember->value.',cycle,member', 'throttle:sensitive'])->name('members.substitute');
        });

        Route::whereNumber(['cycle', 'assessment', 'assignment', 'substitution', 'discussion'])->prefix('{cycle}/secretary/requests')->name('secretary.')->group(function (): void {
            Route::get('/', [SecretaryDeskController::class, 'index'])->middleware('can:'.Ability::ReviewSecretaryDesk->value.',cycle')->name('index');
            Route::get('{assessment}', [SecretaryDeskController::class, 'show'])->middleware('can:'.Ability::ReviewSecretaryDesk->value.',cycle,assessment')->name('show');
            Route::get('{assessment}/evaluations/{assignment}', [SecretaryDeskController::class, 'evaluation'])->middleware('can:'.Ability::ViewSecretaryEvaluation->value.',cycle,assessment,assignment')->name('evaluation');
            Route::post('{assessment}/average', [SecretaryDecisionController::class, 'recompute'])->middleware(['can:'.Ability::DecideSecretaryDesk->value.',cycle,assessment', 'throttle:sensitive'])->name('average');
            Route::post('{assessment}/approve', [SecretaryDecisionController::class, 'approve'])->middleware(['can:'.Ability::DecideSecretaryDesk->value.',cycle,assessment', 'throttle:sensitive'])->name('approve');
            Route::post('{assessment}/return', [SecretaryDecisionController::class, 'return'])->middleware(['can:'.Ability::DecideSecretaryDesk->value.',cycle,assessment', 'throttle:sensitive'])->name('return');
            Route::post('{assessment}/substitutions/{substitution}/approve', [SecretaryDecisionController::class, 'approveSubstitution'])->middleware(['can:'.Ability::ApproveSubstitution->value.',cycle,assessment,substitution', 'throttle:sensitive'])->name('substitutions.approve');
            Route::post('{assessment}/discussions', [SecretaryDiscussionController::class, 'store'])->middleware(['can:'.Ability::DecideSecretaryDesk->value.',cycle,assessment', 'throttle:sensitive'])->name('discussions.store');
            Route::post('{assessment}/discussions/{discussion}/close', [SecretaryDiscussionController::class, 'close'])->middleware(['can:'.Ability::CloseCommitteeDiscussion->value.',cycle,assessment,discussion', 'throttle:sensitive'])->name('discussions.close');
        });

        Route::whereNumber(['cycle', 'assessment', 'region'])->group(function (): void {
            Route::get('{cycle}/results', [AwardResultsController::class, 'regional'])->middleware('can:'.Ability::ViewAwardResults->value.',cycle')->name('results.index');
            Route::put('{cycle}/results/{assessment}/regional-rank', [AwardResultsController::class, 'rankRegional'])->middleware(['can:'.Ability::RankAwardResult->value.',cycle,assessment', 'throttle:sensitive'])->name('results.regional-rank');
            Route::post('{cycle}/regions/{region}/approve', [AwardResultsController::class, 'approveRegion'])->middleware(['can:'.Ability::ApproveRegionResults->value.',cycle,region', 'throttle:sensitive'])->name('regions.approve');
            Route::post('{cycle}/regions/{region}/unapprove', [AwardResultsController::class, 'unapproveRegion'])->middleware(['can:'.Ability::UnapproveRegionResults->value.',cycle,region', 'throttle:sensitive'])->name('regions.unapprove');
            Route::get('{cycle}/national', [AwardResultsController::class, 'national'])->middleware('can:'.Ability::ViewAwardResults->value.',cycle')->name('national.index');
            Route::put('{cycle}/national/{assessment}/rank', [AwardResultsController::class, 'rankNational'])->middleware(['can:'.Ability::RankAwardResult->value.',cycle,assessment', 'throttle:sensitive'])->name('national.rank');
            Route::post('{cycle}/national/approve', [AwardResultsController::class, 'approveNational'])->middleware(['can:'.Ability::ApproveNationalResults->value.',cycle', 'throttle:sensitive'])->name('national.approve');
        });
    });

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])
    ->prefix('committee/me')
    ->name('committee.me.')
    ->whereNumber(['assignment', 'discussion'])
    ->group(function (): void {
        $work = 'can:'.Ability::ViewOwnCommitteeWork->value;

        Route::get('assignments', [MyCommitteeWorkController::class, 'assignments'])->middleware($work)->name('assignments.index');
        Route::get('assignments/{assignment}', [MyCommitteeWorkController::class, 'show'])->middleware('can:view,assignment')->name('assignments.show');
        Route::put('assignments/{assignment}/scores', [MyCommitteeEvaluationController::class, 'scores'])->middleware(['can:work,assignment', 'throttle:assessment-write'])->name('assignments.scores');
        Route::put('assignments/{assignment}/notes', [MyCommitteeEvaluationController::class, 'notes'])->middleware(['can:work,assignment', 'throttle:assessment-write'])->name('assignments.notes');
        Route::post('assignments/{assignment}/submit', [MyCommitteeEvaluationController::class, 'submit'])->middleware(['can:work,assignment', 'throttle:sensitive'])->name('assignments.submit');
        Route::get('discussions', [MyCommitteeWorkController::class, 'discussions'])->middleware($work)->name('discussions.index');
        Route::post('discussions/{discussion}/entries', [MyCommitteeWorkController::class, 'respond'])->middleware(['can:respond,discussion', 'throttle:sensitive'])->name('discussions.entries');
    });
