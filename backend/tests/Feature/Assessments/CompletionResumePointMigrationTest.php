<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Notification::fake();
});

function completionResumePointMigration(): object
{
    return require database_path('migrations/2026_11_24_120000_add_resume_point_to_assessment_completion_requests_table.php');
}

function completionResumeChecks(): int
{
    return DB::table('pg_constraint')->whereIn('conname', [
        'assessment_completion_requests_resumes_to_check',
        'assessment_completion_requests_review_days_remaining_check',
    ])->count();
}

it('drops the resume point on rollback and adds it back after', function (): void {
    $migration = completionResumePointMigration();

    $migration->down();

    expect(Schema::hasColumn('assessment_completion_requests', 'resumes_to'))->toBeFalse()
        ->and(Schema::hasColumn('assessment_completion_requests', 'review_days_remaining'))->toBeFalse()
        ->and(completionResumeChecks())->toBe(0);

    $migration->up();

    expect(Schema::hasColumn('assessment_completion_requests', 'resumes_to'))->toBeTrue()
        ->and(Schema::hasColumn('assessment_completion_requests', 'review_days_remaining'))->toBeTrue()
        ->and(completionResumeChecks())->toBe(2);
});

it('accepts only the assessor states as a resume point, and remaining days only with one', function (array $values): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = submittedAdvancedRequest($cycle, $version);

    expect(fn (): bool => DB::table('assessment_completion_requests')->insert([
        'assessment_id' => $assessment->id,
        'notes' => RETURN_NOTES,
        'items' => json_encode([['sub_code' => 'M1.1', 'kind' => 'cv', 'note' => null]]),
        'deadline_at' => now()->addDays(7),
        'expiry_outcome' => 'decline',
        'status' => 'open',
        'requested_by_user_id' => requestFollower()->id,
        'created_at' => now(),
        'updated_at' => now(),
        ...$values,
    ]))->toThrow(QueryException::class);
})->with([
    'a queue state' => [['resumes_to' => 'awaiting_review_assignment']],
    'days without a resume point' => [['review_days_remaining' => 3]],
    'negative days' => [['resumes_to' => 'assigned', 'review_days_remaining' => -1]],
]);
