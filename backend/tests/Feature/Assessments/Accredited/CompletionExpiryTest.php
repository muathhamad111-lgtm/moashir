<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Assessments\AccreditedPurchaseCheck;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const COMPLETION_DEADLINE = '2026-11-08T08:00:00+00:00';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

function returnedForCompletion(CriteriaVersion $version, User $assessor, string $outcome): Assessment
{
    $assessment = reviewedAccreditedRequest($version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى إرفاق محضر اعتماد مجلس الإدارة للسياسة وتحديث وصفها.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']],
        'deadline_at' => COMPLETION_DEADLINE,
        'expiry_outcome' => $outcome,
    ])->assertOk()->assertJsonPath('data.status', 'needs_completion');

    return $assessment->refresh();
}

function expireCompletionRequests(): void
{
    Artisan::call('moashir:assessments:expire-completion-requests');
}

/**
 * @return list<string>
 */
function noticeCodesSentTo(User $user): array
{
    return Notification::sent($user, EntityNotification::class)
        ->map(static fn (EntityNotification $notification): string => $notification->notice->code->value)
        ->values()
        ->all();
}

function completionRequestOf(Assessment $assessment): AssessmentCompletionRequest
{
    return AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->sole();
}

it('stores the expiry outcome and shows it on the entity form, the review and the request detail', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = returnedForCompletion($version, $assessor, 'decline');

    expect(completionRequestOf($assessment)->expiry_outcome->value)->toBe('decline')
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCompletionRequested->value)->sole()->new_values['expiry_outcome'] ?? null)->toBe('decline');

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.completion_request.expiry_outcome', 'decline')
        ->assertJsonPath('data.completion_request.deadline_at', COMPLETION_DEADLINE);
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.completion_request.expiry_outcome', 'decline');
    switchTo(requestFollower())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.completion_requests.0.expiry_outcome', 'decline')
        ->assertJsonPath('data.completion_requests.0.status', 'open');
});

it('changes nothing before the deadline', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = returnedForCompletion($version, assessorUser(), 'decline');

    Date::setTestNow('2026-11-08 07:59:00');
    expireCompletionRequests();

    expect($assessment->refresh()->status->value)->toBe('needs_completion')
        ->and(completionRequestOf($assessment)->status->value)->toBe('open');
});

it('returns the request to the assessor review with the submitted data when the outcome is continue as submitted', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = returnedForCompletion($version, $assessor, 'continue_as_submitted');
    $owner = ownerOf($assessment->entity);
    $follower = requestFollower();

    Date::setTestNow('2026-11-08 08:01:00');
    expireCompletionRequests();

    $request = completionRequestOf($assessment);
    $transition = AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->latest('id')->firstOrFail();

    expect($assessment->refresh()->status->value)->toBe('under_review')
        ->and($request->status->value)->toBe('expired_continued')
        ->and($request->resolved_at?->toIso8601String())->toBe('2026-11-08T08:01:00+00:00')
        ->and($transition->verb->value)->toBe('expire_completion')
        ->and($transition->from_status)->toBe('needs_completion')
        ->and($transition->actor_user_id)->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCompletionExpired->value)->sole()->new_values)->toMatchArray([
            'status' => 'under_review',
            'expiry_outcome' => 'continue_as_submitted',
            'completion_request_id' => $request->id,
        ])
        ->and(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->active()->sole()->assessor_user_id)->toBe($assessor->id)
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedCompletionExpired))->toBeTrue()
        ->and(financeNoticeSent($assessor, NotificationCode::AccreditedCompletionExpired))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AccreditedCompletionExpired))->toBeTrue();

    $history = switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'under_review')->json('data.status_history');

    expect(end($history))->toMatchArray(['from' => 'needs_completion', 'to' => 'under_review', 'verb' => 'expire_completion', 'actor' => 'system']);

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'وصف متأخر للسياسة.']])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
});

it('declines the request at the deadline without a refund and informs the entity, the assessor and the followers', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = returnedForCompletion($version, $assessor, 'decline');
    $owner = ownerOf($assessment->entity);
    $follower = requestFollower();

    Date::setTestNow('2026-11-08 08:01:00');
    expireCompletionRequests();

    $assignment = AssessmentAssignment::query()->where('assessment_id', $assessment->id)->sole();

    expect($assessment->refresh()->status->value)->toBe('declined_incomplete')
        ->and($assessment->isOpen())->toBeFalse()
        ->and(completionRequestOf($assessment)->status->value)->toBe('expired_declined')
        ->and($assignment->status->value)->toBe('ended')
        ->and($assignment->end_reason)->toBe('declined_incomplete')
        ->and(Audit::query()->where('event', AuditEvent::AssessmentDeclinedIncomplete->value)->sole()->new_values)->toMatchArray([
            'status' => 'declined_incomplete',
            'expiry_outcome' => 'decline',
        ])
        ->and(Payment::query()->whereKey($assessment->payment_id)->sole()->status->value)->toBe('paid')
        ->and(Refund::query()->count())->toBe(0)
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedCompletionExpired))->toBeTrue()
        ->and(financeNoticeSent($assessor, NotificationCode::AccreditedCompletionExpired))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AccreditedCompletionExpired))->toBeTrue();

    switchTo($owner)->getJson('/api/v1/assessments/accredited/current')->assertOk()
        ->assertJsonPath('data.active', null)
        ->assertJsonPath('data.latest_closed.status', 'declined_incomplete');
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()->assertJsonPath('data.available', false);
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo($follower)->getJson('/api/v1/requests?filter[status]=declined_incomplete')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $assessment->id)
        ->assertJsonPath('meta.stats.by_status.declined_incomplete', 1);
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'under_review', 'reason' => SUPER_ADMIN_REASON])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('lets the entity buy a new accredited assessment after a decline', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = returnedForCompletion($version, assessorUser(), 'decline');

    Date::setTestNow('2026-11-08 08:01:00');
    expireCompletionRequests();

    app(AccreditedPurchaseCheck::class)->assertMayStart($assessment->entity);
    $next = openedAccreditedRequest($assessment->entity);

    expect($next->id)->not->toBe($assessment->id)
        ->and($next->status->value)->toBe('draft');
});

it('applies the outcome once when the job runs twice', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = returnedForCompletion($version, $assessor, 'decline');

    Date::setTestNow('2026-11-08 08:01:00');
    expireCompletionRequests();
    expireCompletionRequests();
    Date::setTestNow('2026-11-08 09:00:00');
    expireCompletionRequests();

    expect(AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->where('verb', 'expire_completion')->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentDeclinedIncomplete->value)->count())->toBe(1)
        ->and(array_count_values(noticeCodesSentTo($assessor))['ACC-12'] ?? 0)->toBe(1)
        ->and(array_count_values(noticeCodesSentTo(ownerOf($assessment->entity)))['ACC-12'] ?? 0)->toBe(1);
});

it('keeps a completion submitted just before the deadline under review', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = returnedForCompletion($version, $assessor, 'decline');
    $owner = ownerOf($assessment->entity);

    Date::setTestNow('2026-11-08 07:59:00');
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'وصف محدث للسياسة المعتمدة.']])->assertOk();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');

    Date::setTestNow('2026-11-08 08:01:00');
    expireCompletionRequests();

    expect($assessment->refresh()->status->value)->toBe('under_review')
        ->and(completionRequestOf($assessment)->status->value)->toBe('resubmitted')
        ->and(noticeCodesSentTo($owner))->not->toContain('ACC-12')
        ->and(noticeCodesSentTo($assessor))->not->toContain('ACC-12');
});

it('refuses entity changes once the deadline passed and before the job ran', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = returnedForCompletion($version, assessorUser(), 'decline');
    $owner = ownerOf($assessment->entity);

    Date::setTestNow('2026-11-08 08:00:30');

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'وصف متأخر للسياسة.']])
        ->assertConflict()->assertJsonPath('code', 'completion.deadline_passed');
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertConflict()->assertJsonPath('code', 'completion.deadline_passed');
    uploadEvidence($owner, $assessment, $version, 'M1.1', 'board_minutes')->assertConflict()->assertJsonPath('code', 'completion.deadline_passed');

    expect($assessment->refresh()->status->value)->toBe('needs_completion');
});

it('pauses the deadline while the entity is frozen and applies the outcome after the resumed deadline', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = returnedForCompletion($version, assessorUser(), 'decline');
    $entity = $assessment->entity;

    Date::setTestNow('2026-11-04 08:00:00');
    softDeleteWithOpenRequests($entity, [$assessment->request_number]);

    Date::setTestNow('2026-11-10 08:00:00');
    expireCompletionRequests();

    expect($assessment->refresh()->status->value)->toBe('needs_completion');

    restoreEntity($entity);

    expect(completionRequestOf($assessment)->deadline_at->toIso8601String())->toBe('2026-11-14T08:00:00+00:00');

    expireCompletionRequests();

    expect($assessment->refresh()->status->value)->toBe('needs_completion');

    Date::setTestNow('2026-11-14 08:01:00');
    expireCompletionRequests();

    expect($assessment->refresh()->status->value)->toBe('declined_incomplete')
        ->and(completionRequestOf($assessment)->status->value)->toBe('expired_declined');
});

it('waits for the criteria update of a restored entity before applying the outcome', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = returnedForCompletion($version, assessorUser(), 'decline');
    $assessment->forceFill(['criteria_update_pending_since' => Date::now()])->save();

    Date::setTestNow('2026-11-08 08:01:00');
    expireCompletionRequests();

    expect($assessment->refresh()->status->value)->toBe('needs_completion');

    $assessment->forceFill(['criteria_update_pending_since' => null])->save();
    expireCompletionRequests();

    expect($assessment->refresh()->status->value)->toBe('declined_incomplete');
});
