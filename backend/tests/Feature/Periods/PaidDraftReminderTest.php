<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    publishedAccreditedCriteria();
});

it('lets a holder of requests.assign remind the entity of a paid draft at most once a day', function (): void {
    $draft = openedAccreditedRequest();
    $follower = requestFollower();

    switchTo($follower)->postJson("/api/v1/requests/{$draft->id}/remind")->assertOk()
        ->assertJsonPath('data.assessment_id', $draft->id)
        ->assertJsonPath('data.reminded_at', '2027-02-01T07:00:00+00:00')
        ->assertJsonPath('data.next_remind_at', '2027-02-02T07:00:00+00:00');

    expect(financeNoticeSent(ownerOf($draft->entity), NotificationCode::AccreditedPaidDraftReminder))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::RequestReminderSent->value)->where('user_id', $follower->id)->count())->toBe(1);

    atRiyadh('2027-02-02 09:59');
    switchTo($follower)->postJson("/api/v1/requests/{$draft->id}/remind")
        ->assertConflict()
        ->assertJsonPath('code', 'request.reminder_too_soon')
        ->assertJsonPath('meta.next_remind_at', '2027-02-02T07:00:00+00:00');

    atRiyadh('2027-02-02 10:00');
    switchTo($follower)->postJson("/api/v1/requests/{$draft->id}/remind")->assertOk();
});

it('refuses to remind a request that is not a paid draft', function (): void {
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $submitted = submittedAccreditedRequest($version);

    switchTo(requestFollower())->postJson("/api/v1/requests/{$submitted->id}/remind")
        ->assertConflict()
        ->assertJsonPath('code', 'request.not_paid_draft');

    expect($submitted->refresh()->paid_draft_reminded_at)->toBeNull();
});

it('denies the reminder to users without requests.assign and hides it from other paths', function (): void {
    $draft = openedAccreditedRequest();
    test()->postJson("/api/v1/requests/{$draft->id}/remind")->assertUnauthorized();

    switchTo(staffUser(RoleKey::FinanceOfficer))->postJson("/api/v1/requests/{$draft->id}/remind")->assertForbidden();
    switchTo(assessorUser())->postJson("/api/v1/requests/{$draft->id}/remind")->assertForbidden();
    switchTo(ownerOf($draft->entity))->postJson("/api/v1/requests/{$draft->id}/remind")->assertForbidden();
    switchTo(followerOfPaths(AssessmentPath::Advanced))->postJson("/api/v1/requests/{$draft->id}/remind")->assertNotFound();

    expect($draft->refresh()->paid_draft_reminded_at)->toBeNull()
        ->and(financeNoticeSent(ownerOf($draft->entity), NotificationCode::AccreditedPaidDraftReminder))->toBeFalse();
});

it('shows the reminder state and the end of the current period on the preparing tab', function (): void {
    $draft = openedAccreditedRequest();
    $follower = requestFollower();

    switchTo($follower)->getJson('/api/v1/requests/preparing')->assertOk()
        ->assertJsonPath('data.0.period_state', 'pending')
        ->assertJsonPath('data.0.period', null)
        ->assertJsonPath('data.0.period_label.en', 'Set on submission')
        ->assertJsonPath('data.0.current_period_ends_on', '2027-03-31')
        ->assertJsonPath('data.0.last_reminded_at', null)
        ->assertJsonPath('data.0.can_remind', true)
        ->assertJsonPath('data.0.next_remind_at', null);

    switchTo($follower)->postJson("/api/v1/requests/{$draft->id}/remind")->assertOk();

    switchTo($follower)->getJson('/api/v1/requests/preparing')->assertOk()
        ->assertJsonPath('data.0.last_reminded_at', '2027-02-01T07:00:00+00:00')
        ->assertJsonPath('data.0.can_remind', false)
        ->assertJsonPath('data.0.next_remind_at', '2027-02-02T07:00:00+00:00');

    $viewer = staffUser(RoleKey::ContentOfficer);
    $viewer->givePermissionTo(PermissionKey::RequestsQueueView->value);

    atRiyadh('2027-02-03 10:00');
    switchTo($viewer)->getJson('/api/v1/requests/preparing')->assertOk()->assertJsonPath('data.0.can_remind', false);
    switchTo($follower)->getJson('/api/v1/requests/preparing')->assertOk()->assertJsonPath('data.0.can_remind', true);
});

it('filters the preparing tab by the period in which the draft was opened', function (): void {
    atRiyadh('2026-12-20 10:00');
    $early = openedAccreditedRequest();
    atRiyadh('2027-02-01 10:00');
    $current = openedAccreditedRequest();
    $follower = requestFollower();
    $q1 = periodByKey('2027-Q1')->id;

    expect(collect(switchTo($follower)->getJson("/api/v1/requests/preparing?filter[period_id]={$q1}")->assertOk()->json('data'))->pluck('id')->all())->toBe([$current->id])
        ->and(collect(switchTo($follower)->getJson('/api/v1/requests/preparing?filter[period_id]=none')->assertOk()->json('data'))->pluck('id')->all())->toBe([$early->id]);

    switchTo($follower)->getJson('/api/v1/requests/preparing?filter[period_id]=999999')->assertUnprocessable()->assertJsonValidationErrors('filter.period_id');
    switchTo($follower)->getJson('/api/v1/requests/preparing?filter[period_id]=Q1')->assertUnprocessable()->assertJsonValidationErrors('filter.period_id');
});
