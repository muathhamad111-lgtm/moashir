<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Jobs\Assessments\SettleRestoredCriteriaUpdate;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 09:00:00');
});

/**
 * @return array{0: Assessment, 1: Entity}
 */
function heldDraftOfRestoredEntity(): array
{
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    republishWithNewSubWeights('all');
    Queue::fake();
    restoreEntity($entity);

    return [$draft->refresh(), $entity];
}

function sweepHolds(): void
{
    test()->artisan('moashir:assessments:sweep-criteria-update-holds')->assertSuccessful();
}

it('schedules the sweep every ten minutes', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(static fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'moashir:assessments:sweep-criteria-update-holds'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('*/10 * * * *');
});

it('leaves holds younger than thirty minutes alone', function (): void {
    [$draft] = heldDraftOfRestoredEntity();
    Date::setTestNow(Date::now()->addMinutes(29));

    sweepHolds();

    Queue::assertNotPushed(SettleRestoredCriteriaUpdate::class);
    expect($draft->refresh()->criteria_update_redispatches)->toBe(0);
});

it('re-dispatches the criteria update of a stale hold and audits each re-dispatch once', function (): void {
    [$draft, $entity] = heldDraftOfRestoredEntity();
    Date::setTestNow(Date::now()->addMinutes(31));

    sweepHolds();

    Queue::assertPushed(SettleRestoredCriteriaUpdate::class, static fn (SettleRestoredCriteriaUpdate $job): bool => $job->entityId === $entity->id);
    $audits = Audit::query()->where('event', AuditEvent::AssessmentCriteriaUpdateRedispatched->value)->get();

    expect($audits)->toHaveCount(1)
        ->and($audits->first()?->auditable_id)->toBe($entity->id)
        ->and($audits->first()?->new_values)->toEqual(['trigger' => 'entity_restore', 'sweep' => 1, 'assessment_ids' => [$draft->id]])
        ->and($draft->refresh()->criteria_update_redispatches)->toBe(1);
});

it('releases the hold when the re-dispatched job runs, so later sweeps do nothing', function (): void {
    [$draft, $entity] = heldDraftOfRestoredEntity();
    Date::setTestNow(Date::now()->addMinutes(31));
    sweepHolds();

    app()->call([new SettleRestoredCriteriaUpdate($entity->id), 'handle']);

    expect($draft->refresh()->criteria_update_pending_since)->toBeNull()
        ->and($draft->criteria_update_redispatches)->toBe(0)
        ->and($draft->criteria_version_id)->toBe(CriteriaVersion::query()->published()->sole()->id);

    Date::setTestNow(Date::now()->addMinutes(10));
    sweepHolds();

    Queue::assertPushed(SettleRestoredCriteriaUpdate::class, 1);
});

it('alerts the system administrators once after three sweeps without release', function (): void {
    $admin = systemAdmin();
    [$draft] = heldDraftOfRestoredEntity();

    foreach (range(1, 4) as $sweep) {
        Date::setTestNow(Date::now()->addMinutes(31));
        sweepHolds();
    }

    $alerts = Notification::sent($admin, EntityNotification::class)
        ->filter(static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed);

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()?->notice->body->en)->toContain('still pending after 3 automatic retries')
        ->and($alerts->first()?->notice->viaMail)->toBeFalse();
    expect($draft->refresh()->criteria_update_redispatches)->toBe(4)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCriteriaUpdateRedispatched->value)->count())->toBe(4);
});

it('skips the drafts of an entity that was deleted again', function (): void {
    [, $entity] = heldDraftOfRestoredEntity();
    softDeleteWithOpenRequests($entity->refresh(), [Assessment::query()->where('entity_id', $entity->id)->value('request_number')]);
    Date::setTestNow(Date::now()->addMinutes(31));

    sweepHolds();

    Queue::assertNotPushed(SettleRestoredCriteriaUpdate::class);
});

it('releases every hold through the failure path when the re-dispatched job fails for good', function (): void {
    $admin = systemAdmin();
    [$draft, $entity] = heldDraftOfRestoredEntity();

    new SettleRestoredCriteriaUpdate($entity->id)->failed(new RuntimeException('Worker stopped.'));

    expect($draft->refresh()->criteria_update_pending_since)->toBeNull();
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::RequestProcessingFailed);
});
