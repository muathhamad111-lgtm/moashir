<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('lists the advanced requests in the follower queue by path, cycle and status', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $advanced = submittedAdvancedRequest($cycle, $version);
    advancedDraft($cycle);
    $accredited = submittedAccreditedRequest($version);
    $follower = requestFollower();

    switchTo($follower)->getJson('/api/v1/requests')->assertOk()
        ->assertJsonPath('meta.path', 'accredited')
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.stats.by_status.submitted', 1)
        ->assertJsonPath('meta.path_counts.0.total', 1)
        ->assertJsonPath('meta.path_counts.1.path', 'advanced')
        ->assertJsonPath('meta.path_counts.1.awaiting_assignment', 1);
    test()->getJson('/api/v1/requests?filter[path]=advanced')->assertOk()
        ->assertJsonPath('meta.stats.by_status.awaiting_review_assignment', 1)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $advanced->id)
        ->assertJsonPath('data.0.award_cycle.id', $cycle->id)
        ->assertJsonPath('data.0.region.id', $cycle->regions->firstOrFail()->id);
    test()->getJson("/api/v1/requests?filter[cycle_id]={$cycle->id}")->assertOk()->assertJsonPath('meta.pagination.total', 1);
    test()->getJson('/api/v1/requests?filter[path]=accredited&filter[status]=awaiting_review_assignment')->assertUnprocessable();
    test()->getJson('/api/v1/requests?filter[path]=self')->assertUnprocessable();
    test()->getJson("/api/v1/requests/{$accredited->id}")->assertOk()->assertJsonPath('data.award_cycle', null);
});

it('assigns only an initial assessor linked to the cycle who is not a cycle member', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = submittedAdvancedRequest($cycle, $version);
    $linked = assessorUser();
    $unlinked = assessorUser();
    $memberAssessor = staffUser(RoleKey::FilteringOfficer, RoleKey::Assessor);
    linkInitialAssessor($cycle, $linked);
    linkInitialAssessor($cycle, $memberAssessor);
    addCycleMember($cycle, $memberAssessor, CycleMemberRole::FilteringOfficer);
    $follower = requestFollower();
    $path = "/api/v1/requests/{$assessment->id}/assign";

    switchTo($follower)->getJson("/api/v1/requests/assessors?assessment_id={$assessment->id}")->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $linked->id);
    test()->postJson($path, ['assessor_id' => $unlinked->id])->assertUnprocessable()->assertJsonPath('code', 'assessor.not_in_cycle');
    test()->postJson($path, ['assessor_id' => $memberAssessor->id])->assertUnprocessable()->assertJsonPath('code', 'sod_conflict');
    test()->postJson($path, ['assessor_id' => $follower->id])->assertUnprocessable()->assertJsonPath('code', 'sod_self_assignment');

    test()->postJson($path, ['assessor_id' => $linked->id])->assertOk()
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.due_at', '2026-11-15T08:00:00+00:00')
        ->assertJsonPath('data.assessor.id', $linked->id);

    expect($assessment->refresh()->review_sla_days)->toBe(14)
        ->and(financeNoticeSent($linked, NotificationCode::AdvancedAssigned))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::AssessmentAssigned->value)->where('auditable_id', $assessment->id)->exists())->toBeTrue();
});

it('reassigns the initial assessment to another linked assessor with a reason', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $first = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $first);
    $second = assessorUser();
    linkInitialAssessor($cycle, $second);

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => $second->id, 'reason' => ASSIGNMENT_REASON])->assertOk()
        ->assertJsonPath('data.status', 'under_initial_assessment')
        ->assertJsonPath('data.assessor.id', $second->id);

    expect(financeNoticeSent($second, NotificationCode::AdvancedAssigned))->toBeTrue()
        ->and(financeNoticeSent($first, NotificationCode::AdvancedAssigned))->toBeTrue();
    switchTo($first)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertNotFound();
    switchTo($second)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'edit');
});

it('lets the initial assessor score the request and complete it for filtering', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);
    $follower = requestFollower();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);

    switchTo($assessor)->getJson('/api/v1/requests/assigned?filter[tab]=in_progress&filter[path]=advanced')->assertOk()
        ->assertJsonPath('data.0.id', $assessment->id)
        ->assertJsonPath('data.0.path', 'advanced')
        ->assertJsonPath('data.0.award_cycle.id', $cycle->id);
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'edit');
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertUnprocessable()->assertJsonPath('code', 'scores.incomplete');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => accreditedFeedback($version)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => RETURN_NOTES,
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']],
        'deadline_at' => '2026-11-10T08:00:00+00:00',
        'expiry_outcome' => 'decline',
    ])->assertConflict();

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()
        ->assertJsonPath('data.status', 'awaiting_filtering')
        ->assertJsonPath('data.result', null);

    $snapshot = AssessmentResultSnapshot::query()->where('assessment_id', $assessment->id)->sole();

    expect($snapshot->kind->value)->toBe('initial_assessment')
        ->and($snapshot->percent->toDisplay())->toBe('77.00')
        ->and(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->value('status')->value)->toBe('completed')
        ->and(financeNoticeSent($officer, NotificationCode::AdvancedInitialAssessmentCompleted))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AdvancedInitialAssessmentCompleted))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::AwardInitialAssessmentCompleted->value)->count())->toBe(1);

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'read_only');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertForbidden();
    switchTo($assessor)->getJson('/api/v1/requests/assigned?filter[tab]=completed&filter[path]=advanced')->assertOk()->assertJsonPath('data.0.status', 'awaiting_filtering');
});

it('refuses initial assessment work to other assessors and to the entity', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = assignedAdvancedRequest($cycle, $version, $assessor);

    switchTo(assessorUser())->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertNotFound();
    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertForbidden();
    switchTo(requestFollower())->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertForbidden();
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/evidence")->assertOk()->assertJsonCount(1, 'data');
});

it('refuses initial assessment work on a frozen request', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    softDeleteWithOpenRequests($assessment->entity, [$assessment->request_number]);

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])
        ->assertConflict()->assertJsonPath('code', 'entity.deleted');
});
