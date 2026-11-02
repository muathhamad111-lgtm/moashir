<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Criteria\CycleCriteriaBindings;
use App\Contracts\Identity\CommitteeAppointmentsReader;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CycleCommitteeAssessor;
use App\Services\Award\CommitteeAppointments;
use App\Services\Award\CycleCriteriaVersionBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AwardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CommitteeAppointmentsReader::class, CommitteeAppointments::class);
        $this->app->bind(CycleCriteriaBindings::class, CycleCriteriaVersionBindings::class);
    }

    public function boot(): void
    {
        Route::bind('cycle', static fn (string $value): AwardCycle => AwardCycle::query()
            ->with('regions')
            ->whereKey((int) $value)
            ->firstOrFail());
        Route::bind('member', static fn (string $value): CycleCommitteeAssessor => CycleCommitteeAssessor::query()->whereKey((int) $value)->firstOrFail());
        Route::bind('assignment', static fn (string $value): CommitteeAssignment => CommitteeAssignment::query()->with('member')->whereKey((int) $value)->firstOrFail());
        Route::bind('substitution', static fn (string $value): CommitteeAssignment => CommitteeAssignment::query()->with('member')->whereKey((int) $value)->firstOrFail());
        Route::bind('discussion', static fn (string $value): CommitteeDiscussion => CommitteeDiscussion::query()->whereKey((int) $value)->firstOrFail());
    }
}
