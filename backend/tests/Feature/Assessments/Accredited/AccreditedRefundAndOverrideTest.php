<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use App\Models\Finance\Payment;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function recordAccreditedRefund(Assessment $assessment): void
{
    $payment = Payment::query()->findOrFail($assessment->payment_id);

    switchTo(financeOfficer())->post("/api/v1/finance/payments/{$payment->public_id}/refunds", refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();
}

it('closes an accredited request under review when its fee is refunded and stops the assessor work', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);

    recordAccreditedRefund($assessment);
    $assessment->refresh();

    expect($assessment->status->value)->toBe('refunded')
        ->and($assessment->refunded_at)->not->toBeNull()
        ->and(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->sole()->status->value)->toBe('ended')
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->latest('id')->firstOrFail()->reason)->toBe(REFUND_REASON)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentRefunded->value)->exists())->toBeTrue()
        ->and(financeNoticeSent($assessor, NotificationCode::AccreditedAssigned))->toBeTrue();

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '6'])])->assertNotFound();
    switchTo(ownerOf($assessment->entity))->getJson('/api/v1/assessments/accredited/current')->assertOk()
        ->assertJsonPath('data.active', null)
        ->assertJsonPath('data.latest_closed.status', 'refunded');
});

it('closes a draft whose fee is refunded before submission', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    recordAccreditedRefund($assessment);

    expect($assessment->refresh()->status->value)->toBe('refunded');
});

it('keeps a completed result when its fee is refunded afterwards and records it', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = completedAccreditedRequest($version, assessorUser());

    recordAccreditedRefund($assessment);

    expect($assessment->refresh()->status->value)->toBe('completed')
        ->and(Audit::query()->where('event', AuditEvent::AssessmentRefundAfterCompletion->value)->where('auditable_id', $assessment->id)->exists())->toBeTrue();
});

it('lets only the Super Admin override a completed score with a reason and recomputes the result', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = completedAccreditedRequest($version, $assessor);
    $admin = superAdmin();
    $payload = ['scores' => selfScores($version, ['M1.1' => '10'])];

    switchTo(systemAdmin())->postJson("/api/v1/assessments/{$assessment->id}/scores/override", [...$payload, 'reason' => SUPER_ADMIN_REASON])->assertForbidden();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/scores/override", [...$payload, 'reason' => SUPER_ADMIN_REASON])->assertForbidden();
    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/scores/override", $payload)->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/scores/override", ['scores' => ['subs' => [(string) subOf($version, 'M1.1')->id => null]], 'reason' => SUPER_ADMIN_REASON])->assertUnprocessable();

    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/scores/override", [...$payload, 'reason' => SUPER_ADMIN_REASON])->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.result.total', '1275.00');

    expect(AssessmentResultSnapshot::query()->where('assessment_id', $assessment->id)->orderBy('version')->pluck('kind')->map->value->all())->toBe(['accredited_result', 'override_recompute'])
        ->and(Audit::query()->where('event', AuditEvent::AssessmentScoreOverridden->value)->firstOrFail()->reason)->toBe(SUPER_ADMIN_REASON)
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->latest('id')->firstOrFail()->verb->value)->toBe('override_score');

    Notification::assertSentTo($admin, SecurityAlertNotification::class);
});

it('refuses a score override before the result exists', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($version, assessorUser());

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/scores/override", ['scores' => selfScores($version, ['M1.1' => '10']), 'reason' => SUPER_ADMIN_REASON])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('reopens a completed assessment for its assessor with a reason', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = completedAccreditedRequest($version, $assessor);

    switchTo(requestFollower())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertForbidden();
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => 'قصير'])->assertUnprocessable();
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk()
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.result', null);

    expect(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->active()->value('assessor_user_id'))->toBe($assessor->id)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentReopened->value)->exists())->toBeTrue();

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()->assertJsonPath('data.available', false);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertConflict();
});

it('forces an accredited request back to the queue or the draft with a reason', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($version, assessorUser());
    $admin = superAdmin();

    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'submitted', 'reason' => SUPER_ADMIN_REASON])->assertOk()
        ->assertJsonPath('data.status', 'submitted');
    expect(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->active()->exists())->toBeFalse();

    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'under_review', 'reason' => SUPER_ADMIN_REASON])
        ->assertConflict()->assertJsonPath('code', 'assessment.assignment_required');
    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'completed', 'reason' => SUPER_ADMIN_REASON])
        ->assertConflict()->assertJsonPath('code', 'assessment.result_required');
    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'refunded', 'reason' => SUPER_ADMIN_REASON])->assertUnprocessable();
    switchTo($admin)->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'draft', 'reason' => SUPER_ADMIN_REASON])->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.submitted_at', null);
    switchTo(systemAdmin())->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'submitted', 'reason' => SUPER_ADMIN_REASON])->assertForbidden();
});

it('never forces a refunded request back into the workflow', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    recordAccreditedRefund($assessment);

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'draft', 'reason' => SUPER_ADMIN_REASON])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});
