<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Assessments\AssessmentScore;
use App\Models\Assessments\EvidenceFile;
use App\Models\Entities\FrozenDeadline;
use App\Models\Files\StoredFile;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('lists the open accredited request in the deletion impact and pauses its deadlines while frozen', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى تحديث وصف السياسة المعتمدة.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']],
        'deadline_at' => '2026-11-08T08:00:00+00:00',
        'expiry_outcome' => 'decline',
    ])->assertOk();
    $entity = $assessment->entity;

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/deletion-impact")->assertOk()
        ->assertJsonPath('data.open_requests.0.number', $assessment->request_number)
        ->assertJsonPath('data.open_requests.0.path', 'accredited')
        ->assertJsonPath('data.open_requests.0.status', 'needs_completion');

    softDeleteWithOpenRequests($entity, [$assessment->request_number]);

    expect(FrozenDeadline::query()->where('entity_id', $entity->id)->pluck('attribute')->sort()->values()->all())->toBe(['deadline_at', 'due_at']);

    switchTo(requestFollower())->getJson('/api/v1/requests')->assertOk()->assertJsonPath('meta.pagination.total', 0);

    Date::setTestNow('2026-11-11 08:00:00');
    restoreEntity($entity);

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe('2026-11-25T08:00:00+00:00')
        ->and(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->sole()->deadline_at->toIso8601String())->toBe('2026-11-18T08:00:00+00:00')
        ->and($assessment->status->value)->toBe('needs_completion');
});

it('re-binds an accredited request under review on a reset_open edit and informs the entity and the assessor', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);
    $owner = ownerOf($assessment->entity);

    $next = republishWithNewSubWeights('reset_open');

    expect($assessment->refresh()->criteria_version_id)->toBe($next->id)
        ->and($assessment->status->value)->toBe('under_review')
        ->and(AssessmentScore::query()->where('assessment_id', $assessment->id)->current()->count())->toBe(0)
        ->and(financeNoticeSent($owner, NotificationCode::CriteriaEditedWithScope))->toBeTrue()
        ->and(financeNoticeSent($assessor, NotificationCode::CriteriaEditedWithScope))->toBeTrue();

    $policyId = (string) subOf($next, 'M1.1')->id;

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.criteria.version_id', $next->id)
        ->assertJsonPath("data.cv.{$policyId}", ACCREDITED_CV_TEXT)
        ->assertJsonPath('data.evidence.0.sub_criterion_id', subOf($next, 'M1.1')->id)
        ->assertJsonPath("data.feedback.{$policyId}.strengths", 'وضوح السياسات المعتمدة وربطها بالأهداف الاستراتيجية.');
});

it('never re-binds a completed accredited result', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = completedAccreditedRequest($version, assessorUser());

    republishWithNewSubWeights('all');

    expect($assessment->refresh()->criteria_version_id)->toBe($version->id);
});

it('holds the accredited request of a restored entity until its criteria update ran', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = readyAccreditedDraft($version);
    $entity = $assessment->entity;
    softDeleteWithOpenRequests($entity, [$assessment->request_number]);
    $next = republishWithNewSubWeights('all');

    restoreEntity($entity);

    expect($assessment->refresh()->criteria_version_id)->toBe($next->id)
        ->and($assessment->awaitsCriteriaUpdate())->toBeFalse();

    switchTo(ownerOf($entity))->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
});

it('erases the open accredited requests, the CV and the evidence links on permanent deletion and keeps the payment', function (): void {
    $version = publishedAccreditedCriteria();
    $completed = completedAccreditedRequest($version, assessorUser());
    $entity = $completed->entity;
    $open = openedAccreditedRequest($entity->refresh());
    $completedFile = EvidenceFile::query()->where('assessment_id', $completed->id)->value('stored_file_id');
    softDeleteWithOpenRequests($entity, [$open->request_number]);

    switchTo(superAdmin())->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'حذف نهائي بناءً على طلب الجهة الرسمي وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(Assessment::query()->pluck('id')->all())->toBe([$completed->id])
        ->and(EvidenceFile::query()->count())->toBe(0)
        ->and(StoredFile::withTrashed()->findOrFail($completedFile)->attachable_type)->toBeNull()
        ->and(Payment::query()->whereKey([$completed->payment_id, $open->payment_id])->count())->toBe(2)
        ->and($completed->refresh()->status->value)->toBe('completed');
});

it('finds the assigned accredited request for its assessor in the global search', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    submittedAccreditedRequest($version);

    switchTo($assessor)->getJson('/api/v1/search?q='.urlencode(strtolower($assessment->request_number)))->assertOk()
        ->assertJsonPath('data.1.type', 'requests')
        ->assertJsonPath('data.1.total', 1)
        ->assertJsonPath('data.1.items.0.path', 'accredited');
    switchTo(staffUser(RoleKey::Assessor))->getJson('/api/v1/search?q=acc')->assertOk()->assertJsonPath('data.1.total', 0);
});
