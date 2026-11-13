<?php

declare(strict_types=1);

use App\Actions\Periods\SettleInProgressPeriods;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\EntityPeriodStatus;
use App\Models\Assessments\PeriodNotice;
use App\Models\Entities\Entity;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function periodStatusOf(Entity $entity, string $key): ?string
{
    return EntityPeriodStatus::query()->where('entity_id', $entity->id)->where('assessment_period_id', periodByKey($key)->id)->first()?->status->value;
}

it('records the status of every entity when a period ends', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $missed = entityWithOwner();
    $assessed = completedInPeriod($version, $assessor)->entity;
    $inReview = submitDraft(readyAccreditedDraft($version));
    $refunded = submitDraft(readyAccreditedDraft($version));
    $refunded->forceFill(['status' => AccreditedAssessmentStatus::Refunded, 'refunded_at' => Date::now()])->save();
    $frozen = entityWithOwner();
    softDeleteWithOpenRequests($frozen, []);
    $unverified = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    atRiyadh('2027-03-25 10:00');
    $lateJoiner = entityWithOwner();

    atRiyadh('2027-04-01 00:20');
    expect(closePeriods())->toBe(1);

    expect(periodStatusOf($missed, '2027-Q1'))->toBe('missed')
        ->and(periodStatusOf($assessed, '2027-Q1'))->toBe('assessed')
        ->and(periodStatusOf($inReview->entity, '2027-Q1'))->toBe('in_progress')
        ->and(periodStatusOf($refunded->entity, '2027-Q1'))->toBe('closed_without_result')
        ->and(periodStatusOf($frozen, '2027-Q1'))->toBe('frozen')
        ->and(periodStatusOf($lateJoiner, '2027-Q1'))->toBe('not_required')
        ->and(periodStatusOf($unverified, '2027-Q1'))->toBeNull()
        ->and(periodByKey('2027-Q1')->closed_at)->not->toBeNull()
        ->and(periodByKey('2027-Q2')->closed_at)->toBeNull();
});

it('notifies the entities that missed the period once with PER-03', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $missed = entityWithOwner();
    $submitted = submitDraft(readyAccreditedDraft($version));

    atRiyadh('2027-04-01 00:20');
    closePeriods();
    closePeriods();

    expect(PeriodNotice::query()->where('code', NotificationCode::PeriodMissed)->pluck('entity_id')->all())->toBe([$missed->id]);
    Notification::assertSentToTimes(ownerOf($missed), EntityNotification::class, 1);
    Notification::assertSentTo(ownerOf($missed), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::PeriodMissed
        && $notification->notice->link === '/paths/accredited'
        && str_contains($notification->notice->title->ar, 'الربع الأول 2027'));
    expect(financeNoticeSent(ownerOf($submitted->entity), NotificationCode::PeriodMissed))->toBeFalse();
});

it('records but does not notify a period that ended long before the closing ran', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    $missed = entityWithOwner();

    atRiyadh('2027-04-20 00:20');
    generatePeriods();
    closePeriods();

    expect(periodStatusOf($missed, '2027-Q1'))->toBe('missed')
        ->and(PeriodNotice::query()->where('code', NotificationCode::PeriodMissed)->exists())->toBeFalse();
});

it('settles an in-progress period once its late result is completed', function (): void {
    atRiyadh('2027-03-30 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $submitted = submitDraft(readyAccreditedDraft($version));

    atRiyadh('2027-04-01 00:20');
    generatePeriods();
    closePeriods();
    expect(periodStatusOf($submitted->entity, '2027-Q1'))->toBe('in_progress');

    atRiyadh('2027-04-03 10:00');
    switchTo(requestFollower())->postJson("/api/v1/requests/{$submitted->id}/assign", ['assessor_id' => $assessor->id])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$submitted->id}/start-review")->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$submitted->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$submitted->id}/review/feedback", ['feedback' => accreditedFeedback($version)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$submitted->id}/complete")->assertOk();

    expect(app(SettleInProgressPeriods::class)->handle())->toBe(1)
        ->and(periodStatusOf($submitted->entity, '2027-Q1'))->toBe('assessed')
        ->and(app(SettleInProgressPeriods::class)->handle())->toBe(0);
});

it('runs the closing from the console command', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    entityWithOwner();
    atRiyadh('2027-04-01 00:20');

    test()->artisan('moashir:periods:close')->assertSuccessful()->expectsOutputToContain('Closed 1 periods');
    test()->artisan('moashir:periods:close')->assertSuccessful()->expectsOutputToContain('Closed 0 periods');
});
