<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('rolls the excellence-tools migrations back and forward again', function (string $file, string $table): void {
    $migration = require database_path('migrations/'.$file.'.php');

    $migration->down();
    expect(Schema::hasTable($table))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'assessor summaries' => ['2026_11_05_160000_create_assessment_summaries_table', 'assessment_summaries'],
    'maturity texts' => ['2026_11_05_160100_create_maturity_interpretations_table', 'maturity_interpretations'],
]);

it('keeps the shared versioning guard after the summaries table is rolled back', function (): void {
    $migration = require database_path('migrations/2026_11_05_160000_create_assessment_summaries_table.php');

    $migration->down();

    expect(DB::scalar("select count(*) from pg_proc where proname = 'moashir_guard_supersede_once'"))->toBe(1);
    $migration->up();
});

it('seeds one empty generic text per maturity band', function (): void {
    expect(DB::table('maturity_interpretations')->whereNull('main_criterion_code')->orderBy('id')->pluck('band')->all())
        ->toBe(['emerging', 'developing', 'mature', 'leading'])
        ->and(DB::table('maturity_interpretations')->whereNotNull('text')->count())->toBe(0);
});
