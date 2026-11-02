<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Listeners\Assessments\RebindAssessmentsAfterEntityRestore;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Entities\EntityNotification;
use App\Services\Assessments\MissedCriteriaEditsReader;
use Illuminate\Database\QueryException;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

const HOLD_OVERRIDE_REASON = 'مراجعة إدارية لمسودة جهة مستعادة بطلب رسمي.';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 09:00:00');
});

/**
 * @return array{0: Assessment, 1: Entity}
 */
function restoredDraftAwaitingRebind(string $scope = 'all'): array
{
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    Date::setTestNow(Date::now()->addHour());
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    republishWithNewSubWeights($scope);
    Queue::fake();
    Date::setTestNow(Date::now()->addHour());
    restoreEntity($entity);

    return [$draft->refresh(), $entity];
}

function queuedRestoreRebind(): CallQueuedListener
{
    $job = Queue::pushed(CallQueuedListener::class, static fn (CallQueuedListener $job): bool => $job->class === RebindAssessmentsAfterEntityRestore::class)->last();
    expect($job)->toBeInstanceOf(CallQueuedListener::class);

    $restored = unserialize(serialize($job));
    $restored->setJob(new FakeJob);

    return $restored;
}

function publishedVersionId(): int
{
    return CriteriaVersion::query()->published()->sole()->id;
}

it('holds the open drafts of the restored entity in the restore transaction', function (): void {
    [$draft, $entity] = restoredDraftAwaitingRebind();

    expect($draft->criteria_update_pending_since?->toIso8601String())->toBe($entity->refresh()->restored_at?->toIso8601String());

    switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$draft->id}")
        ->assertOk()
        ->assertJsonPath('data.criteria_update_in_progress', true)
        ->assertJsonPath('data.is_frozen', false);
});

it('refuses to submit a held draft while the queued re-binding is pending, in both languages', function (string $locale): void {
    [$draft, $entity] = restoredDraftAwaitingRebind();

    switchTo(ownerOf($entity))->withHeader('Accept-Language', $locale)->postJson("/api/v1/assessments/{$draft->id}/submit")
        ->assertConflict()
        ->assertJsonPath('code', 'assessment.criteria_update_in_progress')
        ->assertJsonPath('message', __('errors.assessment.criteria_update_in_progress', [], $locale));

    expect($draft->refresh()->status->value)->toBe('draft')
        ->and($draft->submitted_at)->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::AssessmentSubmitted->value)->exists())->toBeFalse();
})->with(['ar', 'en']);

it('refuses the submission of a held draft to the Super Admin as well', function (): void {
    [$draft] = restoredDraftAwaitingRebind();

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$draft->id}/submit", ['reason' => HOLD_OVERRIDE_REASON])
        ->assertConflict()
        ->assertJsonPath('code', 'assessment.criteria_update_in_progress');
});

it('still lets the owner save the held draft', function (): void {
    [$draft, $entity] = restoredDraftAwaitingRebind();
    $bound = CriteriaVersion::query()->findOrFail($draft->criteria_version_id);

    switchTo(ownerOf($entity))->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($bound, SELF_FULL_MARKS_OF_10)])
        ->assertOk()
        ->assertJsonPath('data.assessment.criteria_update_in_progress', true);
});

it('releases the hold when the queued re-binding finishes, so the draft is submitted on the new version', function (): void {
    [$draft, $entity] = restoredDraftAwaitingRebind();
    $next = publishedVersionId();

    queuedRestoreRebind()->handle(app());

    expect($draft->refresh()->criteria_version_id)->toBe($next)
        ->and($draft->criteria_update_pending_since)->toBeNull();

    $owner = ownerOf($entity);
    switchTo($owner)->getJson("/api/v1/assessments/{$draft->id}")->assertOk()->assertJsonPath('data.criteria_update_in_progress', false);
    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores(CriteriaVersion::query()->findOrFail($next), SELF_FULL_MARKS_OF_10)])
        ->assertOk()
        ->assertJsonPath('data.criteria_version_id', $next);
});

it('releases the hold idempotently when the queued re-binding runs twice', function (): void {
    [$draft] = restoredDraftAwaitingRebind();
    $job = queuedRestoreRebind();

    $job->handle(app());
    $job->handle(app());

    expect($draft->refresh()->criteria_update_pending_since)->toBeNull()
        ->and($draft->criteria_version_id)->toBe(publishedVersionId())
        ->and(Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->count())->toBe(1);
});

it('releases the hold of a draft that had nothing to follow', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    Queue::fake();
    restoreEntity($entity);

    expect($draft->refresh()->awaitsCriteriaUpdate())->toBeTrue();

    queuedRestoreRebind()->handle(app());

    expect($draft->refresh()->awaitsCriteriaUpdate())->toBeFalse()
        ->and($draft->criteria_version_id)->toBe($version->id);
});

it('keeps the hold while an attempt of the queued re-binding fails', function (): void {
    [$draft, $entity] = restoredDraftAwaitingRebind();
    $job = queuedRestoreRebind();
    app()->bind(MissedCriteriaEditsReader::class, static fn (): never => throw new RuntimeException('Criteria history unavailable.'));

    expect(fn () => $job->handle(app()))->toThrow(RuntimeException::class);

    expect($draft->refresh()->awaitsCriteriaUpdate())->toBeTrue();

    switchTo(ownerOf($entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertConflict();
});

it('releases the hold, audits it and alerts the System Administrators when the re-binding fails after its retries', function (): void {
    $admin = systemAdmin();
    [$draft, $entity] = restoredDraftAwaitingRebind();
    $bound = $draft->criteria_version_id;

    queuedRestoreRebind()->failed(new RuntimeException('Criteria history unavailable.'));

    $audit = Audit::query()->where('event', AuditEvent::AssessmentCriteriaUpdateAbandoned->value)->sole();

    expect($draft->refresh()->awaitsCriteriaUpdate())->toBeFalse()
        ->and($draft->criteria_version_id)->toBe($bound)
        ->and($audit->auditable_id)->toBe($draft->id)
        ->and($audit->old_values)->toBe(['criteria_update_pending' => true])
        ->and($audit->new_values)->toEqual(['criteria_update_pending' => false, 'criteria_version_id' => $bound, 'trigger' => 'entity_restore']);

    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed
        && $channels === [InAppChannel::class]
        && str_contains($notification->notice->body->en, $entity->reference())
        && str_contains($notification->notice->body->ar, $entity->reference()));
    Notification::assertNotSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed);
    Notification::assertNotSentTo(staffUser(RoleKey::RequestFollower), EntityNotification::class);

    switchTo(ownerOf($entity))->postJson("/api/v1/assessments/{$draft->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.criteria_version_id', $bound);
});

it('keeps the hold on a draft whose entity is deleted again before the job, and settles it after the next restore', function (): void {
    [$draft, $entity] = restoredDraftAwaitingRebind();
    $firstJob = queuedRestoreRebind();
    softDeleteWithOpenRequests($entity, [$draft->request_number]);

    $firstJob->handle(app());

    expect($draft->refresh()->awaitsCriteriaUpdate())->toBeTrue();

    restoreEntity($entity);
    queuedRestoreRebind()->handle(app());

    expect($draft->refresh()->awaitsCriteriaUpdate())->toBeFalse()
        ->and($draft->criteria_version_id)->toBe(publishedVersionId());
});

it('never holds a closed result of the restored entity', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $closed = startedSelfAssessment(ownerOf($entity));
    $this->postJson("/api/v1/assessments/{$closed->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();
    softDeleteWithOpenRequests($entity, []);
    Queue::fake();

    restoreEntity($entity);

    expect($closed->refresh()->awaitsCriteriaUpdate())->toBeFalse();
});

it('rejects a submitted assessment that still carries the hold at the database level', function (): void {
    [$draft] = restoredDraftAwaitingRebind();

    expect(fn () => DB::table('assessments')->where('id', $draft->id)->update(['status' => 'submitted', 'submitted_at' => Date::now()]))
        ->toThrow(QueryException::class, 'assessments_criteria_update_hold_check');
});
