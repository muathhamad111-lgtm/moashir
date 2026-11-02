<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentCvEntry;
use App\Models\Audit\Audit;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('serves the accredited form without any score and with the bound criteria, CV, evidence and limits', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $policy = subOf($version, 'M1.1');

    $response = switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.assessment.status', 'draft')
        ->assertJsonPath('data.criteria.version_id', $version->id)
        ->assertJsonPath('data.evidence', [])
        ->assertJsonPath('data.completion_request', null)
        ->assertJsonPath('data.upload_limits', ['max_file_mb' => 20, 'allowed_types' => ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png'], 'max_files_per_sub' => 10])
        ->assertJsonPath('data.missing.missing_attachments', [['sub_criterion_id' => $policy->id, 'code' => 'M1.1', 'attachment_key' => POLICY_DOCUMENT_KEY]]);

    expect($response->json('data'))->not->toHaveKeys(['scores', 'progress', 'scale'])
        ->and(collect($response->json('data.missing.missing_cv'))->pluck('code')->all())->toBe(['M1.1', 'M1.2']);
});

it('saves CV entries per sub-criterion and clears an entry sent as null', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $policyId = (string) subOf($version, 'M1.1')->id;

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertOk()
        ->assertJsonPath("data.cv.{$policyId}", ACCREDITED_CV_TEXT);

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [$policyId => null]])->assertOk()
        ->assertJsonMissingPath("data.cv.{$policyId}");

    expect(AssessmentCvEntry::query()->where('assessment_id', $assessment->id)->pluck('sub_code')->all())->toBe(['M1.2'])
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCvSaved->value)->count())->toBe(2)
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedDraftSaved))->toBeTrue();
});

it('validates CV entries against the bound criteria', function (string $code, string $error): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $subId = $code === 'unknown' ? 999_999 : subOf($version, $code)->id;

    switchTo(ownerOf($assessment->entity))->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) $subId => ACCREDITED_CV_TEXT]])
        ->assertUnprocessable()
        ->assertJsonPath('code', $error)
        ->assertJsonValidationErrors(["cv.{$subId}"]);
})->with([
    'sub without CV' => ['M2.1', 'cv.not_allowed'],
    'sub of another version' => ['unknown', 'assessment.sub_not_in_version'],
]);

it('rejects an empty or oversized CV payload', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    switchTo(ownerOf($assessment->entity))->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => []])->assertUnprocessable()->assertJsonValidationErrors(['cv']);
    switchTo(ownerOf($assessment->entity))->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => ['1' => str_repeat('م', 10001)]])->assertUnprocessable()->assertJsonValidationErrors(['cv.1']);
});

it('keeps the accredited draft to its own entity', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    switchTo(ownerOf(entityWithOwner()))->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertNotFound();
    switchTo(ownerOf(entityWithOwner()))->getJson("/api/v1/assessments/{$assessment->id}/form")->assertNotFound();
    switchTo(requestFollower())->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertForbidden();
    switchTo(assessorUser())->getJson("/api/v1/assessments/{$assessment->id}/form")->assertNotFound();
});

it('requires a reason when the Super Admin edits the CV of an entity', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $admin = superAdmin();

    switchTo($admin)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    switchTo($admin)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version), 'reason' => SUPER_ADMIN_REASON])->assertOk();

    Notification::assertSentTo($admin, SecurityAlertNotification::class);
});

it('refuses to submit an accredited draft with missing CV entries or required attachments', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $policyId = subOf($version, 'M1.1')->id;

    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/assessments/{$assessment->id}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'submission.incomplete')
        ->assertJsonValidationErrors(["cv.{$policyId}", "attachments.{$policyId}.".POLICY_DOCUMENT_KEY]);

    expect($assessment->refresh()->status->value)->toBe('draft');
});

it('submits a complete accredited draft, starts the review clock and notifies the entity and the followers', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    $assessment = readyAccreditedDraft($version);
    $owner = ownerOf($assessment->entity);
    $follower = requestFollower();

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.submitted_at', '2026-11-01T08:00:00+00:00');

    $assessment->refresh();

    expect($assessment->due_at?->toIso8601String())->toBe('2026-11-15T08:00:00+00:00')
        ->and($assessment->review_sla_days)->toBe(14)
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedSubmitted))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AccreditedSubmitted))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AccreditedAwaitingAssignment))->toBeTrue();

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('refuses entity writes on the accredited request of a deleted entity', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = readyAccreditedDraft($version);
    $owner = ownerOf($assessment->entity);

    $assessment->entity->delete();

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertConflict()->assertJsonPath('code', 'entity.deleted');
});

it('blocks the submission while the criteria update of a restored entity is pending', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = readyAccreditedDraft($version);
    $assessment->forceFill(['criteria_update_pending_since' => Date::now()])->save();

    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/assessments/{$assessment->id}/submit")
        ->assertConflict()
        ->assertJsonPath('code', 'assessment.criteria_update_in_progress');
    switchTo(ownerOf($assessment->entity))->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertOk();
});

it('lists the accredited request in the entity history without assessor data', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);

    $response = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.path', 'accredited')
        ->assertJsonPath('data.result', null);

    expect(collect($response->json('data.status_history'))->pluck('verb')->all())->toBe(['start', 'open_draft', 'submit'])
        ->and($response->json('data.status_history.0'))->not->toHaveKey('actor_staff');

    switchTo(ownerOf($assessment->entity))->getJson('/api/v1/assessments?filter[path]=accredited&filter[status]=submitted')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});

it('keeps the staff read scope of accredited requests', function (RoleKey $role, int $status): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);

    switchTo(staffUser($role))->getJson("/api/v1/assessments/{$assessment->id}")->assertStatus($status);
})->with([
    'requests follower' => [RoleKey::RequestFollower, 200],
    'system administrator' => [RoleKey::SystemAdmin, 200],
    'unassigned assessor' => [RoleKey::Assessor, 404],
    'finance officer' => [RoleKey::FinanceOfficer, 403],
]);
