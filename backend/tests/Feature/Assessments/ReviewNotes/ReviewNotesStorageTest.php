<?php

declare(strict_types=1);

use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Assessments\AssessmentReviewNote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

const REVIEW_NOTES_MIGRATION = '2026_11_24_100000_create_assessment_review_notes_table';

beforeEach(function (): void {
    Notification::fake();
});

it('keeps the note rows append-only in the database and in the model', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1')])->assertOk();
    $note = AssessmentReviewNote::query()->sole();
    $row = static fn (array $overrides): array => [
        'assessment_id' => $assessment->id, 'level' => 'sub', 'main_criterion_id' => $note->main_criterion_id,
        'sub_criterion_id' => $note->sub_criterion_id, 'author_user_id' => $assessor->id, 'author_role' => 'assessor',
        'body' => 'نسخة ثانية حالية', 'version' => 2, 'created_at' => now(), ...$overrides,
    ];

    expect(fn () => DB::transaction(fn () => DB::table('assessment_review_notes')->where('id', $note->id)->update(['body' => 'نص معدل'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_review_notes')->where('id', $note->id)->update(['superseded_at' => now(), 'body' => 'نص معدل'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_review_notes')->where('id', $note->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::statement('TRUNCATE assessment_review_notes')))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_review_notes')->insert($row([]))))
        ->toThrow(QueryException::class, 'assessment_review_notes_current')
        ->and(fn () => $note->forceFill(['body' => 'نص معدل'])->save())
        ->toThrow(AppendOnlyViolation::class)
        ->and(fn () => $note->delete())
        ->toThrow(AppendOnlyViolation::class);
});

it('enforces the level, unit, body, version and author role checks', function (array $overrides, string $constraint): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $sub = subOf($version, 'M1.1');
    $point = pointOf($version, 'M1.2.1');

    expect(fn () => DB::transaction(fn () => DB::table('assessment_review_notes')->insert([
        'assessment_id' => $assessment->id, 'level' => 'main', 'main_criterion_id' => $sub->main_criterion_id,
        'author_user_id' => $assessor->id, 'author_role' => 'assessor', 'body' => REVIEW_NOTE_BODY, 'version' => 1, 'created_at' => now(),
        ...array_map(static fn (mixed $value): mixed => match ($value) {
            'SUB' => $sub->id,
            'POINT' => $point->id,
            default => $value,
        }, $overrides),
    ])))->toThrow(QueryException::class, $constraint);
})->with([
    'unknown level' => [['level' => 'criterion'], 'assessment_review_notes_level_check'],
    'main with a sub' => [['sub_criterion_id' => 'SUB'], 'assessment_review_notes_unit_check'],
    'sub without a sub' => [['level' => 'sub'], 'assessment_review_notes_unit_check'],
    'sub with a point' => [['level' => 'sub', 'sub_criterion_id' => 'SUB', 'guiding_point_id' => 'POINT'], 'assessment_review_notes_unit_check'],
    'point without a point' => [['level' => 'point', 'sub_criterion_id' => 'SUB'], 'assessment_review_notes_unit_check'],
    'blank body' => [['body' => '   '], 'assessment_review_notes_body_check'],
    'version zero' => [['version' => 0], 'assessment_review_notes_version_check'],
    'unknown author role' => [['author_role' => 'entity'], 'assessment_review_notes_author_role_check'],
]);

it('rolls the notes migration back and forward again and keeps the shared versioning guard', function (): void {
    $migration = require database_path('migrations/'.REVIEW_NOTES_MIGRATION.'.php');

    $migration->down();

    expect(Schema::hasTable('assessment_review_notes'))->toBeFalse()
        ->and(DB::scalar("select count(*) from pg_proc where proname = 'moashir_guard_supersede_once'"))->toBe(1);

    $migration->up();

    expect(Schema::hasTable('assessment_review_notes'))->toBeTrue()
        ->and(DB::scalar("select count(*) from pg_trigger where tgname like 'assessment_review_notes_versioned%'"))->toBe(2);
});

it('erases the notes of every reviewed request of an entity on permanent deletion', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $completed = scoredAccreditedReview($version, $assessor);
    saveReviewNotes($assessor, $completed, [subReviewNote($version, 'M1.1')])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$completed->id}/complete")->assertOk();
    $entity = $completed->entity;
    $openAssessor = assessorUser();
    $open = openedAccreditedRequest($entity->refresh());
    saveReviewNotes(requestFollower(), $open, [mainReviewNote($version, 'M1')])->assertOk();
    $other = reviewedAccreditedRequest($version, $openAssessor);
    saveReviewNotes($openAssessor, $other, [subReviewNote($version, 'M2.1')])->assertOk();
    softDeleteWithOpenRequests($entity, [$open->request_number]);

    switchTo(superAdmin())->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'حذف نهائي بناءً على طلب الجهة الرسمي وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(AssessmentReviewNote::query()->whereIn('assessment_id', [$completed->id, $open->id])->count())->toBe(0)
        ->and(AssessmentReviewNote::query()->where('assessment_id', $other->id)->count())->toBe(1)
        ->and($completed->refresh()->status->value)->toBe('completed');
});
