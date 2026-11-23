<?php

declare(strict_types=1);

use App\Enums\Assessments\AssignmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\UserStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Assessments\AdvancedResubmission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const RESUME_NOTICE_ROOT = 'notifications.assessments.advanced.';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function resumeCompletionPayload(CriteriaVersion $version, string $outcome = 'continue_as_submitted', array $overrides = []): array
{
    return [
        'notes' => RETURN_NOTES,
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv', 'note' => 'أرفقوا محضر اعتماد السياسة.']],
        'deadline_at' => Date::now()->addDays(7)->toIso8601String(),
        'expiry_outcome' => $outcome,
        ...$overrides,
    ];
}

/**
 * @return list<string>
 */
function resumeNoticeKeys(User $user): array
{
    return Notification::sent($user, EntityNotification::class)
        ->map(static fn (EntityNotification $notification): string => (string) $notification->notice->text?->key)
        ->values()
        ->all();
}

function resumeOpenRequestOf(Assessment $assessment): AssessmentCompletionRequest
{
    return AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->latest('id')->firstOrFail();
}

function resumeActiveAssignmentOf(Assessment $assessment): AssessmentAssignment
{
    return AssessmentAssignment::query()->where('assessment_id', $assessment->id)->where('status', AssignmentStatus::Active)->sole();
}

function resumeResubmit(Assessment $assessment, CriteriaVersion $version, string $expectedStatus): void
{
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'أُرفق محضر اعتماد السياسة من مجلس الإدارة.']])->assertOk();
    // The owner reads «under review» for every initial-review state until the announcement (AJ-03).
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');
    expect($assessment->refresh()->status->value)->toBe($expectedStatus);
}

it('lets the initial assessor return a fully scored request to the entity from the review screen', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    scoreEveryUnit($assessor, $assessment);
    Date::setTestNow('2026-11-05 08:00:00');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version))
        ->assertOk()->assertJsonPath('data.status', 'needs_completion');

    $request = resumeOpenRequestOf($assessment);
    $audit = Audit::query()->where('event', AuditEvent::AwardReturnedForCompletion->value)->sole();

    expect($request->resumes_to?->value)->toBe('under_initial_assessment')
        ->and($request->review_days_remaining)->toBe(10)
        ->and(resumeActiveAssignmentOf($assessment)->assessor_user_id)->toBe($assessor->id)
        ->and(resumeNoticeKeys(ownerOf($assessment->entity)))->toContain(RESUME_NOTICE_ROOT.'ADV-25.entity')
        ->and($audit->old_values)->toBe(['status' => 'under_initial_assessment'])
        ->and($audit->new_values['resumes_to'] ?? null)->toBe('under_initial_assessment');

    switchTo(requestFollower())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.completion_requests.0.resumes_to', 'under_initial_assessment');
    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.completion_request.resumes_to', 'under_initial_assessment');
});

it('keeps the request in the assessor list and dashboard as needs completion, read only', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    scoreEveryUnit($assessor, $assessment);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version))->assertOk();

    switchTo($assessor)->getJson('/api/v1/requests/assigned?filter[path]=advanced&filter[tab]=in_progress')->assertOk()
        ->assertJsonPath('data.0.id', $assessment->id)
        ->assertJsonPath('data.0.status', 'needs_completion');
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'read_only');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '5'])])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertConflict();

    expect(boardCards(boardOf($assessor, 'assessor'))->get('awaiting_entity'))->toBe(1);
});

it('refuses the assessor return to anyone but the assignee', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    scoreEveryUnit($assessor, $assessment);
    $path = "/api/v1/assessments/{$assessment->id}/request-completion";

    switchTo(requestFollower())->postJson($path, resumeCompletionPayload($version))->assertForbidden();
    switchTo(assessorUser())->postJson($path, resumeCompletionPayload($version))->assertNotFound();

    expect($assessment->refresh()->status->value)->toBe('under_initial_assessment');
});

it('refuses the assessor return before the initial assessment starts', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = assignedAdvancedRequest($cycle, $version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version))
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    expect($assessment->refresh()->status->value)->toBe('assigned');
});

it('refuses the assessor return while an item is unscored', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '8'])])->assertOk();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version))
        ->assertUnprocessable()->assertJsonPath('code', 'scores.incomplete');

    expect(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->exists())->toBeFalse();
});

it('refuses an assessor deadline after the committee start of the cycle', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    scoreEveryUnit($assessor, $assessment);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version, 'continue_as_submitted', [
        'deadline_at' => $cycle->committee_start->copy()->addDay()->toIso8601String(),
    ]))->assertUnprocessable()->assertJsonPath('code', 'completion.deadline_after_committee_start')->assertJsonValidationErrors('deadline_at');

    expect($assessment->refresh()->status->value)->toBe('under_initial_assessment');
});

it('lets the follower return an assigned or started request without a completeness check, keeping the assignment', function (string $stage, string $resumesTo): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = $stage === 'started' ? startedInitialAssessment($cycle, $version, $assessor) : assignedAdvancedRequest($cycle, $version, $assessor);

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))
        ->assertOk()->assertJsonPath('data.status', 'needs_completion')
        ->assertJsonPath('data.completion_requests.0.resumes_to', $resumesTo);

    expect(resumeOpenRequestOf($assessment)->review_days_remaining)->toBe(14)
        ->and(resumeActiveAssignmentOf($assessment)->assessor_user_id)->toBe($assessor->id);
})->with([
    'assigned' => ['assigned', 'assigned'],
    'under initial assessment' => ['started', 'under_initial_assessment'],
]);

it('keeps the follower return to staff holding the return permission', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    $path = "/api/v1/requests/{$assessment->id}/return-for-completion";

    switchTo($assessor)->postJson($path, resumeCompletionPayload($version))->assertForbidden();
    switchTo(ownerOf($assessment->entity))->postJson($path, resumeCompletionPayload($version))->assertForbidden();
    switchTo(filteringOfficer())->postJson($path, resumeCompletionPayload($version))->assertNotFound();

    expect($assessment->refresh()->status->value)->toBe('under_initial_assessment');
});

it('keeps the pre-assignment return resuming to the assignment queue', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $follower = requestFollower();
    $assessment = submittedAdvancedRequest($cycle, $version);

    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk()
        ->assertJsonPath('data.completion_requests.0.resumes_to', null);

    expect(resumeOpenRequestOf($assessment)->review_days_remaining)->toBeNull();

    resumeResubmit($assessment, $version, 'awaiting_review_assignment');

    expect(resumeNoticeKeys($follower))->toContain(RESUME_NOTICE_ROOT.'ADV-04.resubmitted');
});

it('resumes a resubmitted request to the same assessor with the review days that remained', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $follower = requestFollower();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    scoreEveryUnit($assessor, $assessment);
    Date::setTestNow('2026-11-05 08:00:00');
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version))->assertOk();

    Date::setTestNow('2026-11-08 08:00:00');
    resumeResubmit($assessment, $version, 'under_initial_assessment');

    $audit = Audit::query()->where('event', AuditEvent::AwardResubmitted->value)->sole();

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe('2026-11-18T08:00:00+00:00')
        ->and(resumeActiveAssignmentOf($assessment)->assessor_user_id)->toBe($assessor->id)
        ->and(resumeOpenRequestOf($assessment)->status->value)->toBe('resubmitted')
        ->and(resumeNoticeKeys($assessor))->toContain(RESUME_NOTICE_ROOT.'ADV-05.resubmitted')
        ->and(resumeNoticeKeys($follower))->not->toContain(RESUME_NOTICE_ROOT.'ADV-04.resubmitted')
        ->and($audit->new_values['status'] ?? null)->toBe('under_initial_assessment')
        ->and($audit->new_values['due_at'] ?? null)->toBe('2026-11-18T08:00:00+00:00');

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'edit');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '9'])])->assertOk();
});

it('resumes a request returned before the review started to the assigned state', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = assignedAdvancedRequest($cycle, $version, $assessor);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();

    Date::setTestNow('2026-11-04 08:00:00');
    resumeResubmit($assessment, $version, 'assigned');

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe('2026-11-18T08:00:00+00:00')
        ->and(resumeNoticeKeys($assessor))->toContain(RESUME_NOTICE_ROOT.'ADV-05.resubmitted');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk()->assertJsonPath('data.status', 'under_initial_assessment');
});

it('gives the assessor at least one day when the review was already past due at the return', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    Date::setTestNow('2026-11-20 08:00:00');
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();

    expect(resumeOpenRequestOf($assessment)->review_days_remaining)->toBe(0);

    Date::setTestNow('2026-11-22 08:00:00');
    resumeResubmit($assessment, $version, 'under_initial_assessment');

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe('2026-11-23T08:00:00+00:00');
});

it('shows the entity the needs completion step coming from the initial assessment', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    scoreEveryUnit($assessor, $assessment);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version))->assertOk();
    resumeResubmit($assessment, $version, 'under_initial_assessment');

    $history = collect(switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->json('data.status_history'))
        ->map(static fn (array $entry): string => $entry['from'].'>'.$entry['to'])
        ->all();

    // AJ-03 masks the initial-review states as «under review» for the owner; the completion step stays visible.
    expect($history)->toContain('under_review>needs_completion', 'needs_completion>under_review')
        ->and(implode(',', $history))->not->toContain('under_initial_assessment');
});

it('continues an expired return at the state it left, with the remaining review days', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    Date::setTestNow('2026-11-03 08:00:00');
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();

    Date::setTestNow('2026-11-10 08:00:01');
    Artisan::call('moashir:assessments:expire-completion-requests');

    expect($assessment->refresh()->status->value)->toBe('under_initial_assessment')
        ->and($assessment->due_at?->toIso8601String())->toBe('2026-11-22T08:00:01+00:00')
        ->and(resumeOpenRequestOf($assessment)->status->value)->toBe('expired_continued')
        ->and(resumeActiveAssignmentOf($assessment)->assessor_user_id)->toBe($assessor->id)
        ->and(resumeNoticeKeys($assessor))->toContain(RESUME_NOTICE_ROOT.'ADV-27.continued.assessor');
});

it('declines an expired return after the assignment and ends the assignment', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = assignedAdvancedRequest($cycle, $version, $assessor);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version, 'decline'))->assertOk();

    Date::setTestNow('2026-11-09 08:00:00');
    Artisan::call('moashir:assessments:expire-completion-requests');

    expect($assessment->refresh()->status->value)->toBe('declined_incomplete')
        ->and(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->where('status', AssignmentStatus::Active)->exists())->toBeFalse();
});

// Operator decision 2026-11-23 («السماح في المسارين»): the request may change assessor while the entity completes it.
it('lets the follower reassign while the entity holds the request, and the resubmission resumes with the new assessor', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $second = assessorUser();
    linkInitialAssessor($cycle, $second);
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => $second->id, 'reason' => ASSIGNMENT_REASON])
        ->assertOk()->assertJsonPath('data.status', 'needs_completion');
    resumeResubmit($assessment, $version, 'under_initial_assessment');

    expect(resumeActiveAssignmentOf($assessment)->assessor_user_id)->toBe($second->id)
        ->and(resumeNoticeKeys($second))->toContain(RESUME_NOTICE_ROOT.'ADV-05.resubmitted')
        ->and(resumeNoticeKeys($assessor))->not->toContain(RESUME_NOTICE_ROOT.'ADV-05.resubmitted');
    switchTo($second)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'edit');
    expect(switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '9'])])->status())->toBeIn([403, 404]);
});

it('refuses the reassignment during completion to an assessor and without an assessor to hand over', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $second = assessorUser();
    linkInitialAssessor($cycle, $second);
    $started = startedInitialAssessment($cycle, $version, $assessor);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$started->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();
    $unassigned = submittedAdvancedRequest($cycle, $version);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$unassigned->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();

    switchTo($assessor)->postJson("/api/v1/requests/{$started->id}/reassign", ['assessor_id' => $second->id, 'reason' => ASSIGNMENT_REASON])->assertForbidden();
    switchTo(requestFollower())->postJson("/api/v1/requests/{$unassigned->id}/reassign", ['assessor_id' => $second->id, 'reason' => ASSIGNMENT_REASON])
        ->assertConflict();

    expect(resumeActiveAssignmentOf($started)->assessor_user_id)->toBe($assessor->id);
});

it('lets the follower reassign an accredited request while the entity completes it, and tells the new assessor on resubmission', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $second = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ACCREDITED_FULL_MARKS);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", roundCompletionPayload($version))->assertOk();

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => $second->id, 'reason' => ASSIGNMENT_REASON])
        ->assertOk()->assertJsonPath('data.status', 'needs_completion');
    $owner = ownerOf($assessment->entity);
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'وصف محدث للسياسة المعتمدة.']])->assertOk();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');

    $resubmitted = static fn (User $user): bool => collect(resumeNoticeKeys($user))->contains(static fn (string $key): bool => str_contains($key, 'ACC-06') && str_contains($key, 'resubmitted'));

    expect(resumeActiveAssignmentOf($assessment)->assessor_user_id)->toBe($second->id)
        ->and($resubmitted($second))->toBeTrue()
        ->and($resubmitted($assessor))->toBeFalse();
});

it('keeps the accredited completion request as it was, with no resume point', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    scoreEveryUnit($assessor, $assessment);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", resumeCompletionPayload($version))
        ->assertOk()->assertJsonPath('data.status', 'needs_completion');

    $request = resumeOpenRequestOf($assessment);

    expect($request->resumes_to)->toBeNull()
        ->and($request->review_days_remaining)->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCompletionRequested->value)->count())->toBe(1)
        ->and(resumeNoticeKeys(ownerOf($assessment->entity)))->toContain('notifications.assessments.accredited.ACC-07.entity');

    resumeResubmit($assessment, $version, 'under_review');

    expect(resumeNoticeKeys($assessor))->toContain('notifications.assessments.accredited.ACC-06.resubmitted');
});

it('ships the resubmission notice to the assessor in both languages with the same variables', function (): void {
    foreach (['title', 'body'] as $part) {
        $key = RESUME_NOTICE_ROOT.'ADV-05.resubmitted.'.$part;

        expect(__($key, [], 'ar'))->not->toBe($key)
            ->and(__($key, [], 'en'))->not->toBe($key);
    }

    expect(__(RESUME_NOTICE_ROOT.'ADV-05.resubmitted.body', ['entity' => 'جمعية الأمل الخيرية', 'number' => 'ADV-2026-0001'], 'ar'))
        ->toContain('جمعية الأمل الخيرية')->toContain('ADV-2026-0001');
});

// PM review 2026-11-23: a request resumes with its assessor only while that assessor can still work on it.
it('sends a resubmission to the assignment queue when the assessor can no longer work, ending the stale assignment', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $follower = requestFollower();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();
    $assessor->forceFill(['status' => UserStatus::Suspended])->save();

    resumeResubmit($assessment, $version, 'awaiting_review_assignment');

    $ended = AssessmentAssignment::query()->where('assessment_id', $assessment->id)->sole();

    expect($ended->status)->toBe(AssignmentStatus::Ended)
        ->and($ended->end_reason)->toBe('assessor_unavailable')
        ->and(resumeNoticeKeys($follower))->toContain(RESUME_NOTICE_ROOT.'ADV-04.resubmitted')
        ->and(resumeNoticeKeys($assessor))->not->toContain(RESUME_NOTICE_ROOT.'ADV-05.resubmitted');
});

it('continues an expired return in the assignment queue when the assessor can no longer work', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();
    $assessor->delete();

    Date::setTestNow('2026-11-09 08:00:00');
    Artisan::call('moashir:assessments:expire-completion-requests');

    expect($assessment->refresh()->status->value)->toBe('awaiting_review_assignment')
        ->and(resumeOpenRequestOf($assessment)->status->value)->toBe('expired_continued')
        ->and(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->where('status', AssignmentStatus::Active)->exists())->toBeFalse();
});

it('resubmits to the assignment queue when no completion request is open', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = submittedAdvancedRequest($cycle, $version);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", resumeCompletionPayload($version))->assertOk();
    $locked = Assessment::query()->findOrFail($assessment->id);
    $locked->setRelation('openCompletionRequest', null);

    DB::transaction(static fn (): Assessment => app(AdvancedResubmission::class)->resubmit(ownerOf($assessment->entity), $locked));

    expect($assessment->refresh()->status->value)->toBe('awaiting_review_assignment');
});
