<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function completionRoundMigration(): object
{
    return require database_path('migrations/2026_11_21_100000_add_completion_round_to_review_versions.php');
}

function guardSource(string $function): string
{
    $source = DB::table('pg_proc')->where('proname', $function)->value('prosrc');

    return is_string($source) ? $source : '';
}

function guardAllowsRoundUnlinking(string $function): bool
{
    return str_contains(guardSource($function), 'moashir_round_unlinked_on_erasure');
}

/**
 * The function body as the migration that created it defines it, with the heredoc indentation PHP strips.
 */
function guardSourceCreatedBy(string $migration, string $function): string
{
    $file = (string) file_get_contents(database_path("migrations/{$migration}.php"));
    preg_match('/FUNCTION '.$function.'\(\) RETURNS trigger AS \$\$(.*?)\$\$ LANGUAGE plpgsql/s', $file, $match);

    return (string) preg_replace('/^ {12}/m', '', $match[1] ?? '');
}

it('drops the completion round and restores the original guards on rollback, and adds them back after', function (): void {
    $migration = completionRoundMigration();

    $migration->down();

    expect(Schema::hasColumn('assessment_scores', 'completion_request_id'))->toBeFalse()
        ->and(Schema::hasColumn('assessment_feedback_items', 'completion_request_id'))->toBeFalse()
        ->and(guardAllowsRoundUnlinking('moashir_guard_assessment_scores'))->toBeFalse()
        ->and(guardAllowsRoundUnlinking('moashir_guard_supersede_once'))->toBeFalse()
        ->and(DB::table('pg_proc')->where('proname', 'moashir_round_unlinked_on_erasure')->exists())->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('assessment_scores', 'completion_request_id'))->toBeTrue()
        ->and(Schema::hasColumn('assessment_feedback_items', 'completion_request_id'))->toBeTrue()
        ->and(guardAllowsRoundUnlinking('moashir_guard_assessment_scores'))->toBeTrue()
        ->and(guardAllowsRoundUnlinking('moashir_guard_supersede_once'))->toBeTrue();
});

it('restores the guard functions byte for byte on rollback', function (): void {
    $migration = completionRoundMigration();
    $migration->down();
    $restored = ['moashir_guard_assessment_scores' => guardSource('moashir_guard_assessment_scores'), 'moashir_guard_supersede_once' => guardSource('moashir_guard_supersede_once')];

    $migration->up();
    $migration->down();

    expect($restored['moashir_guard_assessment_scores'])->toBe(guardSourceCreatedBy('2026_11_01_110300_create_assessment_scores_table', 'moashir_guard_assessment_scores'))
        ->and($restored['moashir_guard_supersede_once'])->toBe(guardSourceCreatedBy('2026_11_01_170500_create_assessment_feedback_items_table', 'moashir_guard_supersede_once'))
        ->and(guardSource('moashir_guard_assessment_scores'))->toBe($restored['moashir_guard_assessment_scores'])
        ->and(guardSource('moashir_guard_supersede_once'))->toBe($restored['moashir_guard_supersede_once'])
        ->and(guardSourceCreatedBy('2026_11_01_110300_create_assessment_scores_table', 'moashir_guard_assessment_scores'))->toContain('RAISE EXCEPTION');

    $migration->up();
});

it('still refuses to unlink a score from its completion round outside a permanent entity deletion', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment, $completion] = resubmittedForRoundTwo($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '9']);

    expect(fn () => DB::table('assessment_scores')->where('completion_request_id', $completion->id)->update(['completion_request_id' => null]))
        ->toThrow(QueryException::class, 'append_only');
});
