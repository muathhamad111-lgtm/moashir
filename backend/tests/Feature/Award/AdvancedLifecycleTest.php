<?php

declare(strict_types=1);

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\CycleMemberRole;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Award\FilteringDecision;
use App\Models\Entities\FrozenDeadline;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('freezes an advanced request with its entity and resumes its timers on restore', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = assignedAdvancedRequest($cycle, $version, assessorUser());
    $entity = $assessment->entity;

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/deletion-impact")->assertOk()
        ->assertJsonPath('data.open_requests.0.number', $assessment->request_number)
        ->assertJsonPath('data.open_requests.0.path', 'advanced')
        ->assertJsonPath('data.open_requests.0.cycle_id', $cycle->id)
        ->assertJsonPath('data.open_requests.0.status', 'assigned');

    softDeleteWithOpenRequests($entity, [$assessment->request_number]);

    expect(FrozenDeadline::query()->where('entity_id', $entity->id)->pluck('attribute')->all())->toBe(['due_at']);
    switchTo(requestFollower())->getJson('/api/v1/requests?filter[path]=advanced')->assertOk()->assertJsonPath('meta.pagination.total', 0);

    Date::setTestNow('2026-11-06 08:00:00');
    restoreEntity($entity);

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe('2026-11-20T08:00:00+00:00')
        ->and($assessment->awaitsCriteriaUpdate())->toBeFalse()
        ->and($assessment->status->value)->toBe('assigned');
});

it('pauses the completion deadline of a returned advanced request while frozen', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = submittedAdvancedRequest($cycle, $version);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", [
        'notes' => RETURN_NOTES,
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'evidence']],
        'deadline_at' => '2026-11-10T08:00:00+00:00',
        'expiry_outcome' => 'decline',
    ])->assertOk();
    softDeleteWithOpenRequests($assessment->entity, [$assessment->request_number]);

    Date::setTestNow('2026-11-04 08:00:00');
    restoreEntity($assessment->entity);

    expect(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->sole()->deadline_at->toIso8601String())->toBe('2026-11-13T08:00:00+00:00');
});

it('keeps advanced requests on the criteria version of their cycle when criteria are republished', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $draft = readyAdvancedDraft($cycle, $version);
    $submitted = submittedAdvancedRequest($cycle, $version);
    $accredited = readyAccreditedDraft($version);

    $next = republishWithNewSubWeights('reset_open');

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and($submitted->refresh()->criteria_version_id)->toBe($version->id)
        ->and($accredited->refresh()->criteria_version_id)->toBe($next->id);

    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertOk();
});

it('never holds an advanced draft after the entity is restored', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $draft = readyAdvancedDraft($cycle, $version);
    softDeleteWithOpenRequests($draft->entity, [$draft->request_number]);
    republishWithNewSubWeights('all');

    restoreEntity($draft->entity);

    expect($draft->refresh()->awaitsCriteriaUpdate())->toBeFalse()
        ->and($draft->criteria_version_id)->toBe($version->id);
    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertOk()->assertJsonPath('data.status', 'awaiting_review_assignment');
});

it('records every advanced transition in the status history with the staff names for staff readers only', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = initiallyAssessedRequest($cycle, $version, $assessor);

    $history = switchTo(requestFollower())->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->json('data.status_history');

    expect(array_column($history, 'verb'))->toBe(['start', 'submit', 'assign', 'start_review', 'complete'])
        ->and($history[3]['actor_staff']['name'])->toBe($assessor->name);

    $entityHistory = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->json('data.status_history');

    expect(array_column($entityHistory, 'verb'))->toBe(['start', 'submit', 'assign', 'start_review'])
        ->and($entityHistory[2])->not->toHaveKey('actor_staff');
});

it('erases the open advanced requests on permanent deletion and keeps the closed ones', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);
    $qualified = initiallyAssessedRequest($cycle, $version, assessorUser());
    $entity = $qualified->entity;
    closeCycleSubmissions($cycle);
    switchTo($officer)->postJson("/api/v1/award/cycles/{$cycle->id}/filtering/{$qualified->id}/qualify")->assertOk();
    $previousCycle = draftAwardCycle($version, ['status' => 'closed', 'closed_at' => Date::now(), 'closed_from_status' => 'submission_closed'], $cycle->regions->firstOrFail());
    $withdrawn = Assessment::factory()->forEntity($entity)->boundTo($version)->create([
        'request_number' => 'ADV-2025-000001',
        'path' => AssessmentPath::Advanced,
        'status' => AdvancedAssessmentStatus::Withdrawn,
        'award_cycle_id' => $previousCycle->id,
        'region_id' => $entity->region_id,
        'submitted_at' => Date::now()->subYear(),
        'withdrawn_at' => Date::now()->subMonths(11),
    ]);
    softDeleteWithOpenRequests($entity, [$qualified->request_number]);

    switchTo(superAdmin())->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'حذف نهائي بناءً على طلب الجهة الرسمي وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(Assessment::query()->where('entity_id', $entity->id)->pluck('id')->all())->toBe([$withdrawn->id])
        ->and(FilteringDecision::query()->count())->toBe(0);
});
