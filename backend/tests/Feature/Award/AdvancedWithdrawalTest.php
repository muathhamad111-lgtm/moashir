<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('lets the follower withdraw a submitted request at the entity request with a reason', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    $follower = requestFollower();
    $otherFollower = requestFollower();
    $path = "/api/v1/requests/{$assessment->id}/withdraw";

    switchTo($follower)->postJson($path)->assertUnprocessable()->assertJsonValidationErrors('reason');
    test()->postJson($path, ['reason' => WITHDRAWAL_REASON])->assertOk()->assertJsonPath('data.status', 'withdrawn');
    test()->postJson($path, ['reason' => WITHDRAWAL_REASON])->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    $owner = ownerOf($assessment->entity);

    expect($assessment->refresh()->withdrawn_at)->not->toBeNull()
        ->and(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->value('end_reason'))->toBe('withdrawn')
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->where('verb', 'withdraw')->value('reason'))->toBe(WITHDRAWAL_REASON)
        ->and(Audit::query()->where('event', AuditEvent::AwardWithdrawn->value)->count())->toBe(1)
        ->and(financeNoticeSent($owner, NotificationCode::AdvancedWithdrawn))->toBeTrue()
        ->and(financeNoticeSent($assessor, NotificationCode::AdvancedWithdrawn))->toBeTrue()
        ->and(financeNoticeSent($otherFollower, NotificationCode::AdvancedWithdrawn))->toBeTrue();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertNotFound();
});

it('refuses to withdraw drafts, other paths and requests of unauthorized roles', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $draft = advancedDraft($cycle);
    $submitted = submittedAdvancedRequest($cycle, $version);
    $accredited = submittedAccreditedRequest($version);
    $body = ['reason' => WITHDRAWAL_REASON];

    switchTo(requestFollower())->postJson("/api/v1/requests/{$draft->id}/withdraw", $body)->assertConflict();
    test()->postJson("/api/v1/requests/{$accredited->id}/withdraw", $body)->assertNotFound();
    switchTo(ownerOf($submitted->entity))->postJson("/api/v1/requests/{$submitted->id}/withdraw", $body)->assertForbidden();
    switchTo(assessorUser())->postJson("/api/v1/requests/{$submitted->id}/withdraw", $body)->assertNotFound();
    switchTo(awardSecretary())->postJson("/api/v1/requests/{$submitted->id}/withdraw", $body)->assertNotFound();
    $member = awardSecretary();
    addCycleMember($cycle, $member, CycleMemberRole::AwardSecretary);
    switchTo($member)->postJson("/api/v1/requests/{$submitted->id}/withdraw", $body)->assertForbidden();
});

it('withdraws a frozen request and requires a reason from the Super Admin', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = submittedAdvancedRequest($cycle, $version);
    softDeleteWithOpenRequests($assessment->entity, [$assessment->request_number]);

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/withdraw", ['reason' => WITHDRAWAL_REASON])->assertOk()->assertJsonPath('data.status', 'withdrawn');

    expect(Audit::query()->where('event', AuditEvent::AwardWithdrawn->value)->firstOrFail()->new_values['frozen'])->toBeTrue();
});
