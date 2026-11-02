<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentFeedbackItem;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Assessments\AssessmentScore;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lists the requests assigned to the assessor by tab', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $new = assignedAccreditedRequest($version, $assessor);
    $started = reviewedAccreditedRequest($version, $assessor);
    assignedAccreditedRequest($version, assessorUser());

    switchTo($assessor)->getJson('/api/v1/requests/assigned')->assertOk()->assertJsonPath('meta.pagination.total', 2);
    test()->getJson('/api/v1/requests/assigned?filter[tab]=new')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.0.path', 'accredited')
        ->assertJsonStructure(['data' => [['entity' => ['region' => ['id', 'code', 'name']], 'assigned_at', 'due_at', 'is_overdue']]]);
    test()->getJson('/api/v1/requests/assigned?filter[tab]=in_progress')->assertOk()->assertJsonPath('data.0.id', $started->id);
    test()->getJson('/api/v1/requests/assigned?filter[tab]=late')->assertUnprocessable();

    switchTo(requestFollower())->getJson('/api/v1/requests/assigned')->assertForbidden();
});

it('opens the review in edit mode for the assignee and read only for the followers', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    $policyId = (string) subOf($version, 'M1.1')->id;

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.mode', 'edit')
        ->assertJsonPath('data.assessment.status', 'assigned')
        ->assertJsonPath("data.cv.{$policyId}", ACCREDITED_CV_TEXT)
        ->assertJsonPath('data.evidence.0.scan_status', 'clean')
        ->assertJsonPath('data.scale.kind', 'of_10')
        ->assertJsonPath('data.entity.id', $assessment->entity_id)
        ->assertJsonPath('data.context', []);
    switchTo(requestFollower())->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'read_only');
    switchTo(systemAdmin())->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'read_only');
    switchTo(assessorUser())->getJson("/api/v1/assessments/{$assessment->id}/review")->assertNotFound();
    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/review")->assertForbidden();
});

it('starts the review once and notifies the entity and the followers', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    $owner = ownerOf($assessment->entity);
    $follower = requestFollower();

    switchTo(assessorUser())->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertNotFound();
    switchTo($follower)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertForbidden();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk()->assertJsonPath('data.status', 'under_review');
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk()->assertJsonPath('data.status', 'under_review');

    expect(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->active()->firstOrFail()->started_at)->not->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::AssessmentReviewStarted->value)->count())->toBe(1)
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedReviewStarted))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AccreditedReviewStarted))->toBeTrue();
});

it('saves the assessor scores item by item with the raw input and the normalized percent', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $policyId = (string) subOf($version, 'M1.1')->id;

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => ['subs' => [$policyId => '7.5']]])->assertOk()
        ->assertJsonPath("data.scores.subs.{$policyId}.raw_value", '7.50')
        ->assertJsonPath("data.scores.subs.{$policyId}.percent", '75.00')
        ->assertJsonPath('data.progress', ['scored' => 1, 'required' => 4]);
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.2.1' => '9'])])->assertOk()
        ->assertJsonPath('data.progress.scored', 2);
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => ['subs' => [$policyId => '8']]])->assertOk();

    $rows = AssessmentScore::query()->where('assessment_id', $assessment->id)->where('sub_criterion_id', $policyId)->orderBy('version')->get();

    expect($rows->pluck('version')->all())->toBe([1, 2])
        ->and($rows->last()?->scorer_role->value)->toBe('assessor')
        ->and($rows->last()?->scorer_user_id)->toBe($assessor->id)
        ->and($rows->first()?->superseded_at)->not->toBeNull();

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => ['subs' => [$policyId => '11']]])->assertUnprocessable();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => ['subs' => [(string) subOf($version, 'M1.2')->id => '5']]])
        ->assertUnprocessable()->assertJsonPath('code', 'score.use_points');
});

it('refuses scores before the review starts and from anyone but the assignee', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    $payload = ['scores' => selfScores($version, ['M1.1' => '6'])];

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", $payload)->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo(requestFollower())->putJson("/api/v1/assessments/{$assessment->id}/review/scores", $payload)->assertForbidden();
    switchTo(systemAdmin())->putJson("/api/v1/assessments/{$assessment->id}/review/scores", $payload)->assertForbidden();
    switchTo(ownerOf($assessment->entity))->putJson("/api/v1/assessments/{$assessment->id}/review/scores", $payload)->assertForbidden();
});

it('saves the feedback items per sub-criterion with versions', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $policyId = (string) subOf($version, 'M1.1')->id;

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => [$policyId => ['strengths' => 'سياسات واضحة ومعتمدة.']]])->assertOk()
        ->assertJsonPath("data.feedback.{$policyId}.strengths", 'سياسات واضحة ومعتمدة.')
        ->assertJsonPath("data.feedback.{$policyId}.improvement_points", null);
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => [$policyId => ['strengths' => 'سياسات واضحة ومحدثة.', 'notes' => 'لا توجد ملاحظات إضافية.']]])->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => [$policyId => ['notes' => null]]])->assertOk()
        ->assertJsonPath("data.feedback.{$policyId}.notes", null);

    expect(AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->where('kind', 'strength')->pluck('version')->all())->toBe([1, 2])
        ->and(AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->current()->count())->toBe(1);

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => [$policyId => ['summary' => 'نص']]])->assertUnprocessable();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => ['999999' => ['notes' => 'نص']]])->assertUnprocessable()->assertJsonPath('code', 'assessment.sub_not_in_version');
});

it('returns the request to the entity for completion and accepts only the requested items', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $owner = ownerOf($assessment->entity);
    $policy = subOf($version, 'M1.1');
    $strategy = subOf($version, 'M1.2');
    $deadline = Date::now()->addDays(7)->toIso8601String();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى إرفاق محضر اعتماد مجلس الإدارة للسياسة وتحديث وصفها.',
        'items' => [['sub_criterion_id' => $policy->id, 'kind' => 'cv'], ['sub_criterion_id' => $policy->id, 'kind' => 'evidence', 'note' => 'محضر الاعتماد']],
        'deadline_at' => $deadline,
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk()->assertJsonPath('data.status', 'needs_completion');

    expect(financeNoticeSent($owner, NotificationCode::AccreditedCompletionRequested))->toBeTrue();

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.completion_request.items.0.sub_criterion_id', $policy->id)
        ->assertJsonPath('data.completion_request.status', 'open')
        ->assertJsonPath('data.completion_request.expiry_outcome', 'continue_as_submitted');
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) $strategy->id => 'نص جديد للمعيار']])
        ->assertUnprocessable()->assertJsonPath('code', 'completion.item_not_requested');
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) $policy->id => 'وصف محدث للسياسة المعتمدة.']])->assertOk();
    uploadEvidence($owner, $assessment, $version, 'M1.1', 'board_minutes')->assertCreated();
    uploadEvidence($owner, $assessment, $version, 'M1.2', null)->assertUnprocessable()->assertJsonPath('code', 'completion.item_not_requested');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '6'])])->assertConflict();

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');

    expect(financeNoticeSent($assessor, NotificationCode::AccreditedReviewStarted))->toBeTrue();
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()->assertJsonPath('data.completion_request', null);
});

it('validates a completion request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [])->assertUnprocessable()->assertJsonValidationErrors(['notes', 'items', 'deadline_at', 'expiry_outcome']);
    test()->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى استكمال السيرة المطلوبة.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']],
        'deadline_at' => Date::now()->addDays(3)->toIso8601String(),
        'expiry_outcome' => 'cancel',
    ])->assertUnprocessable()->assertJsonValidationErrors(['expiry_outcome']);
    test()->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى استكمال السيرة المطلوبة.',
        'items' => [['sub_criterion_id' => subOf($version, 'M2.1')->id, 'kind' => 'cv']],
        'deadline_at' => Date::now()->addDays(3)->toIso8601String(),
        'expiry_outcome' => 'decline',
    ])->assertUnprocessable()->assertJsonPath('code', 'cv.not_allowed');
    test()->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى استكمال السيرة المطلوبة.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']],
        'deadline_at' => Date::now()->addDays(90)->toIso8601String(),
        'expiry_outcome' => 'decline',
    ])->assertUnprocessable()->assertJsonValidationErrors(['deadline_at']);

    expect($assessment->refresh()->status->value)->toBe('under_review');
});

it('refuses to complete an assessment with missing scores', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '8'])])->assertOk();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertUnprocessable()
        ->assertJsonPath('code', 'scores.incomplete')
        ->assertJsonPath('meta.missing.0.code', 'M1.2.1');

    expect($assessment->refresh()->status->value)->toBe('under_review');
});

it('completes an assessment with scores only because the feedback items are optional', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $owner = ownerOf($assessment->entity);

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

    expect(AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->count())->toBe(0);

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.percent', '77.00')
        ->assertJsonPath('data.feedback.0.code', 'M1.1')
        ->assertJsonPath('data.feedback.0.strengths', null)
        ->assertJsonPath('data.feedback.0.improvement_points', null)
        ->assertJsonPath('data.feedback.0.notes', null);
});

it('completes an assessment with feedback on some sub-criteria only', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $policyId = (string) subOf($version, 'M1.1')->id;

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => [$policyId => ['notes' => 'الشواهد كافية لدعم التقييم.']]])->assertOk();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
});

it('completes the assessment, snapshots the result and shows it to the entity without the assessor identity', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);
    $owner = ownerOf($assessment->entity);
    $follower = requestFollower();

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.status', 'under_review');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

    $snapshot = AssessmentResultSnapshot::query()->where('assessment_id', $assessment->id)->sole();

    expect($snapshot->kind->value)->toBe('accredited_result')
        ->and($snapshot->computed_by_user_id)->toBe($assessor->id)
        ->and($snapshot->total_score->toDisplay())->toBe('1155.00')
        ->and(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->sole()->status->value)->toBe('completed')
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedCompleted))->toBeTrue()
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedFeedbackAvailable))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AccreditedCompleted))->toBeTrue();

    $result = switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.total', '1155.00')
        ->assertJsonPath('data.max_total', '1500.00')
        ->assertJsonPath('data.percent', '77.00')
        ->assertJsonPath('data.feedback.0.code', 'M1.1')
        ->assertJsonPath('data.feedback.0.strengths', 'وضوح السياسات المعتمدة وربطها بالأهداف الاستراتيجية.');

    expect(json_encode($result->json('data'), JSON_UNESCAPED_UNICODE))->not->toContain($assessor->name)
        ->and(json_encode($result->json('data'), JSON_UNESCAPED_UNICODE))->not->toContain('assessor');

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.result.percent', '77.00');
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'read_only');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '2'])])->assertForbidden();
    switchTo($assessor)->getJson('/api/v1/requests/assigned?filter[tab]=completed')->assertOk()->assertJsonPath('data.0.id', $assessment->id);
});

it('blocks completion while the criteria update after a restore is pending', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);
    $assessment->forceFill(['criteria_update_pending_since' => Date::now()])->save();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertConflict()->assertJsonPath('code', 'assessment.criteria_update_in_progress');
});

it('refuses assessor work on a frozen request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $assessment->entity->delete();

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '6'])])
        ->assertConflict()->assertJsonPath('code', 'entity.deleted');
    switchTo($assessor)->getJson('/api/v1/requests/assigned')->assertOk()->assertJsonPath('meta.pagination.total', 0);
});
