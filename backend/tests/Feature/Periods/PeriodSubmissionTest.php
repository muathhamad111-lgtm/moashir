<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Settings\SettingKey;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('attaches the period that contains the submission instant in Asia/Riyadh', function (string $localSubmission, string $key): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $draft = readyAccreditedDraft($version);
    atRiyadh($localSubmission);

    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertOk()
        ->assertJsonPath('data.period_state', 'assigned')
        ->assertJsonPath('data.period.key', $key);

    $draft->refresh();
    expect($draft->period_id)->toBe(periodByKey($key)->id)
        ->and($draft->period_assigned_at?->equalTo(Date::now()))->toBeTrue();
})->with([
    'last minutes of Q1 in Riyadh' => ['2027-03-31 23:30', '2027-Q1'],
    'first minutes of Q2 in Riyadh (still March in UTC)' => ['2027-04-01 00:30', '2027-Q2'],
]);

it('refuses a second submission in an assessed period, keeps the draft and names the next start', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $completed = completedInPeriod($version, assessorUser());
    $draft = readyAccreditedDraft($version, $completed->entity);

    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")
        ->assertConflict()
        ->assertJsonPath('code', 'period.already_assessed')
        ->assertJsonPath('meta.next_submission_from', '2027-04-01')
        ->assertJsonPath('meta.current_period.key', '2027-Q1')
        ->assertJsonPath('meta.next_period.key', '2027-Q2')
        ->assertJsonPath('meta.next_period.notice_on', '2027-03-18');

    expect($draft->refresh()->status)->toBe(AccreditedAssessmentStatus::Draft)
        ->and($draft->period_id)->toBeNull();

    atRiyadh('2027-04-01 09:00');
    expect(submitDraft($draft)->period_id)->toBe(periodByKey('2027-Q2')->id);
});

it('keeps the submission period of a result completed in the next period', function (): void {
    atRiyadh('2027-03-30 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $submitted = submitDraft(readyAccreditedDraft($version));

    atRiyadh('2027-04-05 10:00');
    switchTo(requestFollower())->postJson("/api/v1/requests/{$submitted->id}/assign", ['assessor_id' => $assessor->id])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$submitted->id}/start-review")->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$submitted->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$submitted->id}/review/feedback", ['feedback' => accreditedFeedback($version)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$submitted->id}/complete")->assertOk();

    expect($submitted->refresh()->period_id)->toBe(periodByKey('2027-Q1')->id);

    $next = readyAccreditedDraft($version, $submitted->entity);
    expect(submitDraft($next)->period_id)->toBe(periodByKey('2027-Q2')->id);
});

it('lets the entity submit again when the earlier request of the period closed without a result', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $first = submitDraft(readyAccreditedDraft($version));
    $first->forceFill(['status' => AccreditedAssessmentStatus::Refunded, 'refunded_at' => Date::now()])->save();

    $second = submitDraft(readyAccreditedDraft($version, $first->entity));

    expect($second->period_id)->toBe(periodByKey('2027-Q1')->id);
});

it('labels a request submitted before the rollout as before periodic assessment', function (): void {
    atRiyadh('2026-12-15 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $draft = readyAccreditedDraft($version);

    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertOk()
        ->assertJsonPath('data.period_state', 'pre_periodic')
        ->assertJsonPath('data.period', null)
        ->assertJsonPath('data.period_label.ar', 'قبل التقييم الدوري')
        ->assertJsonPath('data.period_label.en', 'Before periodic assessment');

    expect($draft->refresh()->period_id)->toBeNull();
});

it('creates the missing period on submission when the schedule has not run yet', function (): void {
    atRiyadh('2027-01-05 10:00');
    setPeriodicSetting(SettingKey::PeriodicRolloutFrom, PERIOD_ROLLOUT);
    $version = publishedAccreditedCriteria();

    expect(submitDraft(readyAccreditedDraft($version))->period_id)->toBe(periodByKey('2027-Q1')->id);
});
