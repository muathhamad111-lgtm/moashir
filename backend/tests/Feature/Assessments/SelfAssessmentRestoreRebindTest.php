<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Listeners\Assessments\RebindAssessmentsAfterEntityRestore;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentScore;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaEditEvent;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

const RESTORE_KEPT_AFTER_SUB_WEIGHTS = ['M1.2.1', 'M1.2.2', 'M2.1'];

const RESTORE_KEPT_AFTER_BOTH_EDITS = ['M1.2.1', 'M1.2.2'];

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 09:00:00');
});

function frozenScoredDraft(CriteriaVersion $version, Entity $entity): Assessment
{
    $draft = scoredSelfDraft($version, $entity);
    oneHourLater();
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    oneHourLater();

    return $draft;
}

function oneHourLater(): void
{
    Date::setTestNow(Date::now()->addHour());
}

function reboundAudits(): int
{
    return Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->count();
}

function currentScoreCount(Assessment $assessment): int
{
    return AssessmentScore::query()->where('assessment_id', $assessment->id)->current()->count();
}

function assertEntityToldOfTheReset(Entity $entity, Assessment $draft): void
{
    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::CriteriaEditedWithScope
        && in_array('mail', $channels, true)
        && str_contains($notification->notice->body->en, $draft->request_number));
}

function rebindJobOnTheQueue(): CallQueuedListener
{
    $job = Queue::pushed(CallQueuedListener::class, static fn (CallQueuedListener $job): bool => $job->class === RebindAssessmentsAfterEntityRestore::class)->last();
    expect($job)->toBeInstanceOf(CallQueuedListener::class);

    $restored = unserialize(serialize($job));
    $restored->setJob(new FakeJob);

    return $restored;
}

it('moves a frozen draft to the published version on restore under scope "all" and keeps unchanged scores', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);
    $next = republishWithNewSubWeights('all');

    expect($draft->refresh()->criteria_version_id)->toBe($version->id);

    oneHourLater();
    restoreEntity($entity);

    $audit = Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->sole();

    expect($draft->refresh()->criteria_version_id)->toBe($next->id)
        ->and($draft->status->value)->toBe('draft')
        ->and(currentScoreCodes($draft, $next))->toBe(RESTORE_KEPT_AFTER_SUB_WEIGHTS)
        ->and($audit->old_values)->toBe(['criteria_version_id' => $version->id])
        ->and($audit->new_values)->toMatchArray([
            'criteria_version_id' => $next->id,
            'scope' => 'all',
            'cleared_count' => 1,
            'trigger' => 'entity_restore',
            'edit_event_ids' => [CriteriaEditEvent::query()->sole()->id],
        ])
        ->and($audit->new_values['kept_codes'])->toBe(RESTORE_KEPT_AFTER_SUB_WEIGHTS);

    Notification::assertNotSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::CriteriaEditedWithScope);

    switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.criteria.version_id', $next->id)
        ->assertJsonPath('data.progress', ['scored' => 3, 'required' => 4]);
});

it('moves a frozen draft on restore under scope "reset_open", clears every score and informs the entity', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);
    $next = republishWithNewSubWeights('reset_open');

    restoreEntity($entity);

    expect($draft->refresh()->criteria_version_id)->toBe($next->id)
        ->and(currentScoreCount($draft))->toBe(0)
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->count())->toBe(4)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->sole()->new_values)->toMatchArray([
            'scope' => 'reset_open',
            'kept_codes' => [],
            'cleared_count' => 4,
            'trigger' => 'entity_restore',
        ]);

    assertEntityToldOfTheReset($entity, $draft);
});

it('leaves a frozen draft on its version on restore under scope "new_only"', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);
    republishWithNewSubWeights('new_only');

    restoreEntity($entity);

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and(currentScoreCount($draft))->toBe(4)
        ->and(reboundAudits())->toBe(0);

    switchTo(ownerOf($entity))->postJson("/api/v1/assessments/{$draft->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.criteria_version_id', $version->id)
        ->assertJsonPath('data.percent', '76.67');
});

it('applies the strictest scope of several publications made during the freeze', function (string $firstScope, string $secondScope, string $applied): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);
    republishWithNewSubWeights($firstScope);
    oneHourLater();
    $latest = republishWithSmallerResults($secondScope);

    restoreEntity($entity);
    $draft->refresh();

    if ($applied === 'new_only') {
        expect($draft->criteria_version_id)->toBe($version->id)
            ->and(currentScoreCount($draft))->toBe(4)
            ->and(reboundAudits())->toBe(0);

        return;
    }

    $audit = Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->sole();

    expect($draft->criteria_version_id)->toBe($latest->id)
        ->and($audit->new_values['scope'])->toBe($applied)
        ->and($audit->new_values['edit_event_ids'])->toBe(CriteriaEditEvent::query()->orderBy('id')->pluck('id')->all())
        ->and(currentScoreCodes($draft, $latest))->toBe($applied === 'all' ? RESTORE_KEPT_AFTER_BOTH_EDITS : []);

    if ($applied === 'reset_open') {
        assertEntityToldOfTheReset($entity, $draft);
    }
})->with([
    'new_only then all' => ['new_only', 'all', 'all'],
    'all then new_only' => ['all', 'new_only', 'all'],
    'all then reset_open' => ['all', 'reset_open', 'reset_open'],
    'reset_open then new_only' => ['reset_open', 'new_only', 'reset_open'],
    'reset_open then all' => ['reset_open', 'all', 'reset_open'],
    'new_only twice' => ['new_only', 'new_only', 'new_only'],
]);

it('re-assesses every item changed by any of the publications made during the freeze', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);
    republishWithNewSubWeights('all');
    oneHourLater();
    $latest = republishWithSmallerResults('all');

    restoreEntity($entity);

    $audit = Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->sole();

    expect($draft->refresh()->criteria_version_id)->toBe($latest->id)
        ->and(currentScoreCodes($draft, $latest))->toBe(RESTORE_KEPT_AFTER_BOTH_EDITS)
        ->and($audit->new_values['kept_codes'])->toBe(RESTORE_KEPT_AFTER_BOTH_EDITS)
        ->and($audit->new_values['cleared_count'])->toBe(2);

    switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.progress', ['scored' => 2, 'required' => 4]);
});

it('also moves a draft that a "new_only" publication before the freeze had left on an older version', function (string $scopeDuringFreeze, array $keptCodes): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    oneHourLater();
    republishWithNewSubWeights('new_only');

    expect($draft->refresh()->criteria_version_id)->toBe($version->id);

    oneHourLater();
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    oneHourLater();
    $latest = republishWithSmallerResults($scopeDuringFreeze);
    oneHourLater();

    restoreEntity($entity);

    $audit = Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->sole();

    expect($draft->refresh()->criteria_version_id)->toBe($latest->id)
        ->and(currentScoreCodes($draft, $latest))->toBe($keptCodes)
        ->and($audit->new_values)->toMatchArray([
            'scope' => $scopeDuringFreeze,
            'trigger' => 'entity_restore',
            'edit_event_ids' => CriteriaEditEvent::query()->orderBy('id')->pluck('id')->all(),
            'kept_codes' => $keptCodes,
        ]);
})->with([
    'reset_open during the freeze' => ['reset_open', []],
    'all during the freeze, every item changed by either publication re-assessed' => ['all', RESTORE_KEPT_AFTER_BOTH_EDITS],
]);

it('leaves a draft on its older version when only "new_only" publications replaced it, before or during the freeze', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    oneHourLater();
    republishWithNewSubWeights('new_only');
    oneHourLater();
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    oneHourLater();
    republishWithSmallerResults('new_only');
    oneHourLater();

    restoreEntity($entity);

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and($draft->criteria_update_pending_since)->toBeNull()
        ->and(currentScoreCount($draft))->toBe(4)
        ->and(reboundAudits())->toBe(0);
});

it('changes nothing on restore when no criteria version was published during the freeze', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);

    restoreEntity($entity);

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and(currentScoreCount($draft))->toBe(4)
        ->and(reboundAudits())->toBe(0);
});

it('never moves a closed result of a restored entity', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $closed = startedSelfAssessment(ownerOf($entity));
    $this->postJson("/api/v1/assessments/{$closed->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();
    oneHourLater();
    softDeleteWithOpenRequests($entity, []);
    oneHourLater();
    republishWithNewSubWeights('reset_open');

    restoreEntity($entity);

    expect($closed->refresh()->criteria_version_id)->toBe($version->id)
        ->and($closed->status->value)->toBe('result_available')
        ->and(reboundAudits())->toBe(0);
});

it('re-binds through the queue after a serialization round trip and is idempotent', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);
    $next = republishWithNewSubWeights('all');
    Queue::fake();

    restoreEntity($entity);

    expect($draft->refresh()->criteria_version_id)->toBe($version->id);

    $job = rebindJobOnTheQueue();
    $job->handle(app());
    $job->handle(app());

    expect($draft->refresh()->criteria_version_id)->toBe($next->id)
        ->and(currentScoreCodes($draft, $next))->toBe(RESTORE_KEPT_AFTER_SUB_WEIGHTS)
        ->and(reboundAudits())->toBe(1);
});

it('skips a draft whose entity is deleted again before the queued re-binding runs, and re-binds it on the next restore', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = frozenScoredDraft($version, $entity);
    $next = republishWithNewSubWeights('all');
    Queue::fake();
    restoreEntity($entity);
    $firstJob = rebindJobOnTheQueue();
    oneHourLater();
    softDeleteWithOpenRequests($entity, [$draft->request_number]);

    $firstJob->handle(app());

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and(reboundAudits())->toBe(0);

    oneHourLater();
    restoreEntity($entity);
    rebindJobOnTheQueue()->handle(app());

    expect($draft->refresh()->criteria_version_id)->toBe($next->id)
        ->and(reboundAudits())->toBe(1);
});

it('counts the frozen draft as affected only once it is re-bound after the restore', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    frozenScoredDraft($version, $entity);
    $next = republishWithNewSubWeights('all');

    expect(CriteriaEditEvent::query()->sole()->affected_open_count)->toBe(0);

    restoreEntity($entity);

    switchTo(criteriaOfficer())->getJson("/api/v1/criteria/versions/{$next->id}")
        ->assertOk()
        ->assertJsonPath('data.bound_assessments', 1)
        ->assertJsonPath('data.bound_open_assessments', 1);
});
