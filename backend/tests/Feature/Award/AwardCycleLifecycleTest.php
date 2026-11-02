<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleReminder;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('opens a draft cycle on request and announces it to the entities and the staff', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    $entity = cycleEntity($cycle);
    $regionId = $cycle->regions->firstOrFail()->id;
    $deactivated = entityWithOwner(static fn ($factory) => $factory->deactivated()->state(['region_id' => $regionId]));
    $outside = entityWithOwner();
    $follower = requestFollower();
    $admin = systemAdmin();
    $otherAdmin = systemAdmin();

    switchTo($admin)->postJson("/api/v1/award/cycles/{$cycle->id}/open")->assertOk()
        ->assertJsonPath('data.status', 'submission_open')
        ->assertJsonPath('data.submission_start', '2026-11-01T08:00:00+00:00')
        ->assertJsonPath('data.opened_at', '2026-11-01T08:00:00+00:00');
    test()->postJson("/api/v1/award/cycles/{$cycle->id}/open")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    expect(financeNoticeSent(ownerOf($entity), NotificationCode::AdvancedCycleOpened))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AdvancedCycleOpened))->toBeTrue()
        ->and(financeNoticeSent($otherAdmin, NotificationCode::AdvancedCycleOpened))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::CycleOpened->value)->count())->toBe(1);
    Notification::assertNotSentTo(ownerOf($deactivated), EntityNotification::class);
    Notification::assertNotSentTo(ownerOf($outside), EntityNotification::class);
});

it('runs only one cycle at a time while further cycles may be prepared as drafts', function (): void {
    $version = publishedAccreditedCriteria();
    $running = openAwardCycle($version);
    $next = draftAwardCycle($version);

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$next->id}/open")->assertConflict()
        ->assertJsonPath('code', 'cycle.another_open')
        ->assertJsonPath('meta.award_cycle_id', $running->id);

    expect($next->refresh()->status)->toBe(AwardCycleStatus::Draft);
});

it('refuses to open a cycle whose submission period has passed', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version, [
        'submission_start' => '2026-10-01 06:00:00',
        'submission_end' => '2026-10-20 06:00:00',
    ]);

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$cycle->id}/open")->assertConflict()->assertJsonPath('code', 'cycle.submission_window_passed');
});

it('opens and closes the submissions on the cycle dates and expires the unsubmitted drafts', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version, [
        'submission_start' => '2026-11-01 09:00:00',
        'submission_end' => '2026-11-10 09:00:00',
        'committee_start' => '2026-11-20 09:00:00',
        'committee_end' => '2026-12-20 09:00:00',
        'announcement_at' => null,
    ]);
    $secretary = awardSecretary();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);

    Artisan::call('moashir:award:advance-cycles');
    expect($cycle->refresh()->status)->toBe(AwardCycleStatus::Draft);

    Date::setTestNow('2026-11-01 09:00:00');
    Artisan::call('moashir:award:advance-cycles');
    expect($cycle->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen);

    $draft = advancedDraft($cycle);
    $submitted = submittedAdvancedRequest($cycle, $version);

    Date::setTestNow('2026-11-10 09:00:01');
    Artisan::call('moashir:award:advance-cycles');
    Artisan::call('moashir:award:advance-cycles');

    expect($cycle->refresh()->status)->toBe(AwardCycleStatus::SubmissionClosed)
        ->and($draft->refresh()->status->value)->toBe('expired')
        ->and($submitted->refresh()->status->value)->toBe('awaiting_review_assignment')
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $draft->id)->where('verb', 'expire_draft')->whereNull('actor_user_id')->exists())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::CycleSubmissionsClosed->value)->firstOrFail()->new_values['expired_drafts'])->toBe([$draft->request_number])
        ->and(financeNoticeSent($secretary, NotificationCode::CycleSubmissionsClosed))->toBeTrue();
});

it('closes the submissions early on request', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);

    switchTo(requestFollower())->postJson("/api/v1/award/cycles/{$cycle->id}/close-submissions")->assertForbidden();
    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$cycle->id}/close-submissions")->assertOk()
        ->assertJsonPath('data.status', 'submission_closed')
        ->assertJsonPath('data.submission_end', '2026-11-01T08:00:00+00:00');
});

it('reminds the entities with unsubmitted drafts seven, three and one days before the submission end, once each', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version, ['submission_end' => '2026-11-12 08:00:00']);
    $draft = advancedDraft($cycle);
    submittedAdvancedRequest($cycle, $version);
    $owner = ownerOf($draft->entity);

    Artisan::call('moashir:award:remind-submission-deadline');
    expect(AwardCycleReminder::query()->count())->toBe(0);

    Date::setTestNow('2026-11-05 09:00:00');
    Artisan::call('moashir:award:remind-submission-deadline');
    Artisan::call('moashir:award:remind-submission-deadline');

    expect(AwardCycleReminder::query()->where('days_before', 7)->value('recipients_count'))->toBe(1)
        ->and(financeNoticeSent($owner, NotificationCode::AdvancedDeadlineApproaching))->toBeTrue();
    Notification::assertSentToTimes($owner, EntityNotification::class, 1);

    Date::setTestNow('2026-11-11 10:00:00');
    Artisan::call('moashir:award:remind-submission-deadline');

    expect(AwardCycleReminder::query()->where('award_cycle_id', $cycle->id)->orderBy('days_before')->pluck('recipients_count', 'days_before')->all())->toBe([1 => 1, 3 => 0, 7 => 1]);
    Notification::assertSentToTimes($owner, EntityNotification::class, 2);
});

it('closes a cycle only when every request is terminal and ignores frozen requests', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $secretary = awardSecretary();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);
    $submitted = submittedAdvancedRequest($cycle, $version);
    $frozen = submittedAdvancedRequest($cycle, $version);
    softDeleteWithOpenRequests($frozen->entity, [$frozen->request_number]);
    closeCycleSubmissions($cycle);

    switchTo($secretary)->postJson("/api/v1/award/cycles/{$cycle->id}/close")->assertConflict()
        ->assertJsonPath('code', 'cycle.requests_pending')
        ->assertJsonPath('meta.open_requests_count', 1)
        ->assertJsonPath('meta.open_requests.0.number', $submitted->request_number);

    switchTo(requestFollower())->postJson("/api/v1/requests/{$submitted->id}/withdraw", ['reason' => WITHDRAWAL_REASON])->assertOk();

    $admin = systemAdmin();
    switchTo($secretary)->postJson("/api/v1/award/cycles/{$cycle->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');

    expect($cycle->refresh()->closed_from_status)->toBe(AwardCycleStatus::SubmissionClosed)
        ->and(financeNoticeSent($admin, NotificationCode::CycleClosed))->toBeTrue();
    test()->postJson("/api/v1/award/cycles/{$cycle->id}/close")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('refuses to close a cycle whose submissions are still open', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$cycle->id}/close")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('lets only the Super Admin reopen a closed cycle, with a reason', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = closeCycleSubmissions(openAwardCycle($version));
    $admin = systemAdmin();

    switchTo($admin)->postJson("/api/v1/award/cycles/{$cycle->id}/close")->assertOk();
    test()->postJson("/api/v1/award/cycles/{$cycle->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertForbidden();

    $superAdmin = superAdmin();
    switchTo($superAdmin)->postJson("/api/v1/award/cycles/{$cycle->id}/reopen")->assertUnprocessable()->assertJsonValidationErrors('reason');
    test()->postJson("/api/v1/award/cycles/{$cycle->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk()
        ->assertJsonPath('data.status', 'submission_closed')
        ->assertJsonPath('data.closed_at', null);

    expect(Audit::query()->where('event', AuditEvent::CycleReopened->value)->firstOrFail()->reason)->toBe(SUPER_ADMIN_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, static fn (SecurityAlertNotification $notification): bool => $notification->code() === NotificationCode::ReasonRequiredAction);
    test()->postJson("/api/v1/award/cycles/{$cycle->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertConflict();
});

it('refuses to reopen a closed cycle while another cycle runs', function (): void {
    $version = publishedAccreditedCriteria();
    $closed = draftAwardCycle($version, ['status' => AwardCycleStatus::Closed, 'closed_at' => Date::now(), 'closed_from_status' => AwardCycleStatus::Announced]);
    openAwardCycle($version);

    switchTo(superAdmin())->postJson("/api/v1/award/cycles/{$closed->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertConflict()->assertJsonPath('code', 'cycle.another_open');
});

it('shows the running cycle publicly without internal settings', function (): void {
    $version = publishedAccreditedCriteria();

    test()->getJson('/api/v1/award/cycles/current')->assertOk()->assertJsonPath('data', null);

    draftAwardCycle($version);
    test()->getJson('/api/v1/award/cycles/current')->assertOk()->assertJsonPath('data', null);

    $cycle = openAwardCycle($version);
    test()->getJson('/api/v1/award/cycles/current')->assertOk()
        ->assertJsonPath('data.id', $cycle->id)
        ->assertJsonPath('data.is_accepting_applications', true)
        ->assertJsonPath('data.conditions', ['min_entity_age_years' => 3])
        ->assertJsonPath('data.regions.0.id', $cycle->regions->firstOrFail()->id)
        ->assertJsonMissingPath('data.disqualification_score')
        ->assertJsonMissingPath('data.criteria_version_id');

    expect(AwardCycle::query()->count())->toBe(2)
        ->and(Assessment::query()->count())->toBe(0);
});
