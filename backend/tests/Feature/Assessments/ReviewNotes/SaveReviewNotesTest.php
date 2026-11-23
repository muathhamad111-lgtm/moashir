<?php

declare(strict_types=1);

use App\Actions\Assessments\SaveReviewNotes;
use App\Data\Assessments\ReviewNoteChange;
use App\Data\Assessments\ReviewNoteChanges;
use App\Data\Assessments\ReviewNoteUnit;
use App\Enums\Assessments\ReviewNoteAuthorRole;
use App\Enums\Assessments\ReviewNoteLevel;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\AssessmentReviewNote;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lets the active assessor save sub-criterion and indicator notes and audits them without the text', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);

    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1'), pointReviewNote($version, 'M1.2.1', 'المؤشر يحتاج شاهدًا أحدث.')])
        ->assertOk()
        ->assertJsonPath('data.can_write_review_notes', true)
        ->assertJsonCount(2, 'data.review_notes')
        ->assertJsonPath('data.review_notes.0.level', 'sub')
        ->assertJsonPath('data.review_notes.0.main_criterion_id', mainOf($version, 'M1')->id)
        ->assertJsonPath('data.review_notes.0.sub_criterion_id', subOf($version, 'M1.1')->id)
        ->assertJsonPath('data.review_notes.0.guiding_point_id', null)
        ->assertJsonPath('data.review_notes.0.body', REVIEW_NOTE_BODY)
        ->assertJsonPath('data.review_notes.0.author', ['id' => $assessor->id, 'name' => $assessor->name, 'role' => 'assessor'])
        ->assertJsonPath('data.review_notes.0.is_mine', true)
        ->assertJsonPath('data.review_notes.1.level', 'point')
        ->assertJsonPath('data.review_notes.1.guiding_point_id', pointOf($version, 'M1.2.1')->id)
        ->assertJsonStructure(['data' => ['review_notes' => [['id', 'updated_at']]]]);

    $audit = Audit::query()->where('event', AuditEvent::AssessmentReviewNotesSaved->value)->sole();

    expect(AssessmentReviewNote::query()->current()->where('assessment_id', $assessment->id)->pluck('author_role')->all())
        ->toBe([ReviewNoteAuthorRole::Assessor, ReviewNoteAuthorRole::Assessor])
        ->and($audit->new_values)->toEqual(['changed' => 2, 'levels' => ['sub', 'point']])
        ->and((int) $audit->auditable_id)->toBe($assessment->id)
        ->and(json_encode($audit->toArray(), JSON_UNESCAPED_UNICODE))->not->toContain(REVIEW_NOTE_BODY)->not->toContain('المؤشر يحتاج');

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.can_write_review_notes', true)
        ->assertJsonCount(2, 'data.review_notes');
});

it('lets the requests follower save a main criterion note', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($version, assessorUser());
    $follower = requestFollower();

    saveReviewNotes($follower, $assessment, [mainReviewNote($version, 'M2')])->assertOk()
        ->assertJsonPath('data.review_notes.0.level', 'main')
        ->assertJsonPath('data.review_notes.0.main_criterion_id', mainOf($version, 'M2')->id)
        ->assertJsonPath('data.review_notes.0.sub_criterion_id', null)
        ->assertJsonPath('data.review_notes.0.author.role', 'follower');

    $other = systemAdmin();
    saveReviewNotes($other, $assessment, [mainReviewNote($version, 'M2', 'ملاحظة مدير النظام على المعيار.')])->assertOk();

    expect(AssessmentReviewNote::query()->where('author_user_id', $other->id)->sole()->author_role)->toBe(ReviewNoteAuthorRole::Admin);
});

it('keeps one current note per author on a unit and tells each viewer which note is theirs', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $follower = requestFollower();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1', 'ملاحظة المقيّم.')])->assertOk();
    saveReviewNotes($follower, $assessment, [subReviewNote($version, 'M1.1', 'ملاحظة المتابع.')])->assertOk();

    $asAssessor = switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->json('data.review_notes');
    $asFollower = switchTo($follower)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->json('data.review_notes');
    $mine = static fn (array $notes): array => collect($notes)->mapWithKeys(static fn (array $note): array => [$note['body'] => $note['is_mine']])->all();
    $names = static fn (array $notes): array => collect($notes)->mapWithKeys(static fn (array $note): array => [$note['body'] => $note['author']['name']])->all();

    expect(AssessmentReviewNote::query()->current()->where('assessment_id', $assessment->id)->count())->toBe(2)
        ->and($mine($asAssessor))->toBe(['ملاحظة المقيّم.' => true, 'ملاحظة المتابع.' => false])
        ->and($mine($asFollower))->toBe(['ملاحظة المقيّم.' => false, 'ملاحظة المتابع.' => true])
        ->and($names($asAssessor))->toBe(['ملاحظة المقيّم.' => $assessor->name, 'ملاحظة المتابع.' => null])
        ->and($names($asFollower))->toBe(['ملاحظة المقيّم.' => $assessor->name, 'ملاحظة المتابع.' => $follower->name]);
});

it('supersedes only the author own note, skips unchanged text and clears on a blank body', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $follower = requestFollower();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveReviewNotes($follower, $assessment, [subReviewNote($version, 'M1.1', 'ملاحظة المتابع.')])->assertOk();
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1')])->assertOk();
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1', '  '.REVIEW_NOTE_BODY.'  ')])->assertOk();
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1', 'نص معدل للملاحظة.')])->assertOk();

    $mine = AssessmentReviewNote::query()->where('author_user_id', $assessor->id)->orderBy('id')->get();

    expect($mine->pluck('version')->all())->toBe([1, 2])
        ->and($mine->first()?->superseded_at)->not->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::AssessmentReviewNotesSaved->value)->count())->toBe(3);

    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1', '   ')])->assertOk()
        ->assertJsonCount(1, 'data.review_notes')
        ->assertJsonPath('data.review_notes.0.body', 'ملاحظة المتابع.');
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1', null)])->assertOk();

    expect(AssessmentReviewNote::query()->where('author_user_id', $assessor->id)->current()->count())->toBe(0)
        ->and(AssessmentReviewNote::query()->where('author_user_id', $assessor->id)->count())->toBe(2)
        ->and(AssessmentReviewNote::query()->where('author_user_id', $follower->id)->current()->sole()->version)->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentReviewNotesSaved->value)->count())->toBe(4);
});

it('rejects an invalid payload', function (string $case): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    [$notes, $field] = invalidReviewNotePayload($case, $version);

    switchTo($assessor)->putJson(reviewNotesUrl($assessment), ['notes' => $notes])->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect(AssessmentReviewNote::query()->count())->toBe(0);
})->with(['empty list', 'body too long', 'missing body', 'unknown level', 'main note with a sub', 'sub note without a sub', 'point note without a point', 'duplicate unit']);

it('rejects a unit outside the bound criteria version', function (Closure $note, string $code, string $field): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);

    saveReviewNotes($assessor, $assessment, [$note($version)])->assertUnprocessable()
        ->assertJsonPath('code', $code)
        ->assertJsonValidationErrors([$field]);

    expect(AssessmentReviewNote::query()->count())->toBe(0);
})->with([
    'a main criterion of another version' => [static fn (): array => mainReviewNote(draftAccreditedCriteria(), 'M1'), 'assessment.main_not_in_version', 'notes.0.main_criterion_id'],
    'a sub-criterion under another main' => [static fn ($version): array => [...subReviewNote($version, 'M2.1'), 'main_criterion_id' => mainOf($version, 'M1')->id], 'assessment.sub_not_in_version', 'notes.0.sub_criterion_id'],
    'an inactive sub-criterion' => [static fn ($version): array => subReviewNote($version, 'M2.2'), 'assessment.sub_not_in_version', 'notes.0.sub_criterion_id'],
    'an indicator under a directly evaluated sub-criterion' => [static fn ($version): array => [...pointReviewNote($version, 'M1.2.1'), 'sub_criterion_id' => subOf($version, 'M1.1')->id], 'assessment.point_not_in_version', 'notes.0.guiding_point_id'],
]);

it('requires a reason from the Super Admin and stores it with the note and the audit entry', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($version, assessorUser());
    $superAdmin = superAdmin();

    saveReviewNotes($superAdmin, $assessment, [mainReviewNote($version, 'M1')])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    saveReviewNotes($superAdmin, $assessment, [mainReviewNote($version, 'M1')], 'قصير')->assertUnprocessable()->assertJsonValidationErrors(['reason']);

    saveReviewNotes($superAdmin, $assessment, [mainReviewNote($version, 'M1')], SUPER_ADMIN_REASON)->assertOk()
        ->assertJsonPath('data.review_notes.0.author.role', 'super_admin')
        ->assertJsonPath('data.review_notes.0.is_mine', true);

    $note = AssessmentReviewNote::query()->sole();

    expect($note->author_role)->toBe(ReviewNoteAuthorRole::SuperAdmin)
        ->and($note->change_reason)->toBe(SUPER_ADMIN_REASON)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentReviewNotesSaved->value)->sole()->reason)->toBe(SUPER_ADMIN_REASON);

    switchTo(requestFollower())->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.review_notes.0.author.name', null)
        ->assertJsonPath('data.review_notes.0.author.role', 'super_admin');
});

it('answers 409 on a closed or frozen request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $completed = completedAccreditedRequest($version, $assessor);
    $frozen = reviewedAccreditedRequest($version, assessorUser());
    $frozen->entity->delete();
    $superAdmin = superAdmin();

    saveReviewNotes($superAdmin, $completed, [mainReviewNote($version, 'M1')], SUPER_ADMIN_REASON)->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    saveReviewNotes($superAdmin, $frozen, [mainReviewNote($version, 'M1')], SUPER_ADMIN_REASON)->assertConflict()->assertJsonPath('code', 'entity.deleted');

    expect(fn () => app(SaveReviewNotes::class)->handle(requestFollower(), $completed, new ReviewNoteChanges([
        new ReviewNoteChange(new ReviewNoteUnit(ReviewNoteLevel::Main, mainOf($version, 'M1')->id), REVIEW_NOTE_BODY),
    ]), null))->toThrow(IllegalStateTransition::class)
        ->and(AssessmentReviewNote::query()->count())->toBe(0);
});

it('refuses staff writes on a closed or frozen request and reports that the notes are read only', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $follower = requestFollower();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveReviewNotes($follower, $assessment, [mainReviewNote($version, 'M1')])->assertOk();
    $assessment->entity->delete();

    saveReviewNotes($follower, $assessment, [mainReviewNote($version, 'M1', 'نص جديد.')])->assertForbidden();
    switchTo($follower)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.can_write_review_notes', false)
        ->assertJsonCount(1, 'data.review_notes');

    $completed = completedAccreditedRequest($version, $assessor);

    saveReviewNotes($follower, $completed, [mainReviewNote($version, 'M1')])->assertForbidden();
    switchTo($assessor)->getJson("/api/v1/assessments/{$completed->id}/review")->assertOk()
        ->assertJsonPath('data.can_write_review_notes', false)
        ->assertJsonPath('data.review_notes', []);
});
