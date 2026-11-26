<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

function concurrentCyclesMigration(): object
{
    return require database_path('migrations/2026_11_23_130000_allow_concurrent_award_cycles.php');
}

function hasOneRunningIndex(): bool
{
    return DB::table('pg_indexes')->where('tablename', 'award_cycles')->where('indexname', 'award_cycles_one_running')->exists();
}

it('drops the one-running-cycle index so the setting decides how many cycles run', function (): void {
    $version = publishedAccreditedCriteria();

    expect(hasOneRunningIndex())->toBeFalse();

    openAwardCycle($version);
    openAwardCycle($version);

    expect(DB::table('award_cycles')->where('status', 'submission_open')->count())->toBe(2);
});

it('restores the index on rollback while at most one cycle runs and is safe to run up again', function (): void {
    openAwardCycle(publishedAccreditedCriteria());

    concurrentCyclesMigration()->down();
    expect(hasOneRunningIndex())->toBeTrue();

    concurrentCyclesMigration()->up();
    concurrentCyclesMigration()->up();
    expect(hasOneRunningIndex())->toBeFalse();
});

it('refuses the rollback while several cycles run', function (): void {
    $version = publishedAccreditedCriteria();
    openAwardCycle($version);
    openAwardCycle($version);

    expect(fn () => concurrentCyclesMigration()->down())->toThrow(RuntimeException::class, 'More than one award cycle is running');
    expect(hasOneRunningIndex())->toBeFalse();
});
