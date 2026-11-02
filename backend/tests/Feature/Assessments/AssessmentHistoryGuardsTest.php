<?php

declare(strict_types=1);

use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Assessments\AssessmentScore;
use App\Models\Assessments\AssessmentStatusTransition;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function submittedSelfAssessment(): Assessment
{
    $version = publishedSelfCriteria();
    $assessment = startedSelfAssessment(ownerOf(entityWithOwner()));
    test()->putJson("/api/v1/assessments/{$assessment->id}", ['scores' => selfScores($version, ['M1.1' => '6'])])->assertOk();
    test()->postJson("/api/v1/assessments/{$assessment->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    return $assessment;
}

it('lets a score row change only once, and only to be superseded', function (): void {
    $assessment = submittedSelfAssessment();
    $current = AssessmentScore::query()->where('assessment_id', $assessment->id)->current()->firstOrFail();
    $superseded = AssessmentScore::query()->where('assessment_id', $assessment->id)->whereNotNull('superseded_at')->firstOrFail();

    expect(fn () => DB::transaction(fn () => DB::table('assessment_scores')->where('id', $current->id)->update(['raw_value' => '1.00'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_scores')->where('id', $superseded->id)->update(['superseded_at' => now()->addDay()])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_scores')->where('id', $current->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => $current->forceFill(['raw_value' => '2.00'])->save())
        ->toThrow(AppendOnlyViolation::class);
});

it('keeps the status history and the result snapshots append-only', function (): void {
    $assessment = submittedSelfAssessment();
    $transition = AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->firstOrFail();
    $snapshot = AssessmentResultSnapshot::query()->where('assessment_id', $assessment->id)->firstOrFail();

    expect(fn () => DB::transaction(fn () => DB::table('assessment_status_transitions')->where('id', $transition->id)->update(['to_status' => 'draft'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_result_snapshots')->where('id', $snapshot->id)->update(['percent' => '100.0000'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessments')->where('id', $assessment->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => $transition->forceFill(['reason' => 'تعديل غير مسموح على السجل.'])->save())
        ->toThrow(AppendOnlyViolation::class)
        ->and(fn () => $snapshot->delete())
        ->toThrow(AppendOnlyViolation::class);
});

it('refuses a result without a snapshot and a submitted draft at the database level', function (): void {
    $version = publishedSelfCriteria();
    $assessment = Assessment::factory()->boundTo($version)->create();

    expect(fn () => DB::transaction(fn () => DB::table('assessments')->where('id', $assessment->id)->update(['status' => 'result_available', 'submitted_at' => now(), 'completed_at' => now()])))
        ->toThrow(QueryException::class, 'assessments_result_check')
        ->and(fn () => DB::transaction(fn () => DB::table('assessments')->where('id', $assessment->id)->update(['submitted_at' => now()])))
        ->toThrow(QueryException::class, 'assessments_draft_not_submitted_check');
});

it('accepts deleting the assessment history only inside the permanent-deletion transaction', function (): void {
    $assessment = submittedSelfAssessment();

    DB::transaction(static function () use ($assessment): void {
        DB::select("SELECT set_config('moashir.entity_erasure', 'on', true)");
        DB::table('assessments')->where('id', $assessment->id)->delete();
    });

    expect(Assessment::query()->count())->toBe(0)
        ->and(AssessmentScore::query()->count())->toBe(0)
        ->and(AssessmentStatusTransition::query()->count())->toBe(0)
        ->and(AssessmentResultSnapshot::query()->count())->toBe(0);
});
