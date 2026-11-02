<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const ADVANCED_DEADLINE = '2026-11-15T08:00:00+00:00';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function returnPayload(CriteriaVersion $version, string $outcome = 'continue_as_submitted', array $overrides = []): array
{
    return [
        'notes' => RETURN_NOTES,
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv', 'note' => 'حدّثوا وصف السياسة المعتمدة.']],
        'deadline_at' => ADVANCED_DEADLINE,
        'expiry_outcome' => $outcome,
        ...$overrides,
    ];
}

function returnedAdvancedRequest(AwardCycle $cycle, CriteriaVersion $version, User $follower, string $outcome = 'continue_as_submitted'): Assessment
{
    $assessment = submittedAdvancedRequest($cycle, $version);

    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", returnPayload($version, $outcome))->assertOk()
        ->assertJsonPath('data.status', 'needs_completion');

    return $assessment->refresh();
}

it('returns an incomplete request to the entity with the items, the deadline and the expiry outcome', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $follower = requestFollower();
    $assessment = returnedAdvancedRequest($cycle, $version, $follower);
    $owner = ownerOf($assessment->entity);

    switchTo($follower)->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.completion_requests.0.status', 'open')
        ->assertJsonPath('data.completion_requests.0.expiry_outcome', 'continue_as_submitted')
        ->assertJsonPath('data.completion_requests.0.items.0.code', 'M1.1')
        ->assertJsonPath('data.completion_deadline', ADVANCED_DEADLINE);
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.assessment.status', 'needs_completion')
        ->assertJsonPath('data.completion_request.deadline_at', ADVANCED_DEADLINE);

    expect(financeNoticeSent($owner, NotificationCode::AdvancedReturnedForCompletion))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::AwardReturnedForCompletion->value)->count())->toBe(1);
});

it('validates the return and keeps one open return at a time', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $follower = requestFollower();
    $assessment = submittedAdvancedRequest($cycle, $version);
    $path = "/api/v1/requests/{$assessment->id}/return-for-completion";

    switchTo($follower)->postJson($path, returnPayload($version, 'continue_as_submitted', ['deadline_at' => $cycle->committee_start->copy()->addDay()->toIso8601String()]))
        ->assertUnprocessable()->assertJsonPath('code', 'completion.deadline_after_committee_start')->assertJsonValidationErrors('deadline_at');
    test()->postJson($path, returnPayload($version, 'expire'))->assertUnprocessable()->assertJsonValidationErrors('expiry_outcome');
    test()->postJson($path, returnPayload($version, 'decline', ['items' => []]))->assertUnprocessable()->assertJsonValidationErrors('items');
    test()->postJson($path, returnPayload($version, 'decline', ['deadline_at' => '2026-10-30T08:00:00+00:00']))->assertUnprocessable()->assertJsonValidationErrors('deadline_at');
    test()->postJson($path, returnPayload($version, 'decline', ['items' => [['sub_criterion_id' => subOf($version, 'M2.1')->id, 'kind' => 'cv']]]))
        ->assertUnprocessable()->assertJsonPath('code', 'cv.not_allowed');

    test()->postJson($path, returnPayload($version))->assertOk();
    test()->postJson($path, returnPayload($version))->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    expect(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->count())->toBe(1);
});

it('lets only the followers and the System Administrator return an advanced request', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = submittedAdvancedRequest($cycle, $version);
    $accredited = submittedAccreditedRequest($version);
    $path = "/api/v1/requests/{$assessment->id}/return-for-completion";

    switchTo(assessorUser())->postJson($path, returnPayload($version))->assertNotFound();
    switchTo(filteringOfficer())->postJson($path, returnPayload($version))->assertNotFound();
    switchTo(ownerOf($assessment->entity))->postJson($path, returnPayload($version))->assertForbidden();
    switchTo(requestFollower())->postJson("/api/v1/requests/{$accredited->id}/return-for-completion", returnPayload($version))->assertNotFound();
    switchTo(systemAdmin())->postJson($path, returnPayload($version))->assertOk();
});

it('refuses a return once the initial assessor is assigned', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = assignedAdvancedRequest($cycle, $version, assessorUser());

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", returnPayload($version))
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('lets the entity change only the listed items and resubmit to the follower queue, with unlimited returns', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $follower = requestFollower();
    $assessment = returnedAdvancedRequest($cycle, $version, $follower);
    $owner = ownerOf($assessment->entity);
    $listed = (string) subOf($version, 'M1.1')->id;
    $unlisted = (string) subOf($version, 'M1.2')->id;

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [$unlisted => 'نص جديد للمعيار غير المطلوب.']])
        ->assertUnprocessable()->assertJsonPath('code', 'completion.item_not_requested');
    uploadEvidence($owner, $assessment, $version)->assertUnprocessable()->assertJsonPath('code', 'completion.item_not_requested');
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [$listed => 'تحديث وصف السياسة المعتمدة من مجلس الإدارة.']])->assertOk();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'awaiting_review_assignment');

    expect(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->value('status')->value)->toBe('resubmitted')
        ->and(financeNoticeSent($follower, NotificationCode::AdvancedSubmitted))->toBeTrue();

    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", returnPayload($version, 'decline'))->assertOk();

    expect(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->count())->toBe(2);
});

it('continues the request as submitted at the deadline when the follower chose it', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $follower = requestFollower();
    $assessment = returnedAdvancedRequest($cycle, $version, $follower);
    $owner = ownerOf($assessment->entity);

    Date::setTestNow('2026-11-15 08:00:01');
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'نص متأخر.']])
        ->assertConflict()->assertJsonPath('code', 'completion.deadline_passed');

    Artisan::call('moashir:assessments:expire-completion-requests');
    Artisan::call('moashir:assessments:expire-completion-requests');

    expect($assessment->refresh()->status->value)->toBe('awaiting_review_assignment')
        ->and(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->value('status')->value)->toBe('expired_continued')
        ->and(financeNoticeSent($owner, NotificationCode::AdvancedCompletionExpired))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AdvancedCompletionExpired))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCompletionExpired->value)->count())->toBe(1);
});

it('declines the request at the deadline when the follower chose it and skips frozen requests', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $follower = requestFollower();
    $declined = returnedAdvancedRequest($cycle, $version, $follower, 'decline');
    $frozen = returnedAdvancedRequest($cycle, $version, $follower, 'decline');
    softDeleteWithOpenRequests($frozen->entity, [$frozen->request_number]);

    Date::setTestNow('2026-11-16 08:00:00');
    Artisan::call('moashir:assessments:expire-completion-requests');

    expect($declined->refresh()->status->value)->toBe('declined_incomplete')
        ->and($declined->isOpen())->toBeFalse()
        ->and($frozen->refresh()->status->value)->toBe('needs_completion');

    switchTo(ownerOf($declined->entity))->postJson("/api/v1/assessments/{$declined->id}/submit")->assertConflict();
});
