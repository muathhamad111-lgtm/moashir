<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeAverage;
use App\Models\Award\CommitteeScore;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('withdraws a request up to the secretary approval, voids its committee rows and refuses once approved', function (): void {
    $scenario = committeeScenario(2, 1);
    [$withdrawn, $approved] = $scenario['requests'];
    $assessor = $scenario['assessors'][0];
    submitCommitteeEvaluation($assessor, $withdrawn, $scenario['version'])->assertOk();
    expect($withdrawn->refresh()->status->value)->toBe('awaiting_secretary_approval');

    switchTo(requestFollower())->postJson("/api/v1/requests/{$withdrawn->id}/withdraw", ['reason' => WITHDRAWAL_REASON])->assertOk()->assertJsonPath('data.status', 'withdrawn');
    expect(committeeRowOf($assessor, $withdrawn)->status->value)->toBe('voided');
    switchTo($assessor)->getJson('/api/v1/committee/me/assignments')->assertOk()->assertJsonPath('meta.pagination.total', 1);

    approvedByCommittee($scenario, $approved);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$approved->id}/withdraw", ['reason' => WITHDRAWAL_REASON])->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('blocks the cycle closure while committee requests are open', function (): void {
    $scenario = committeeScenario(1, 1);

    switchTo($scenario['secretary'])->postJson("/api/v1/award/cycles/{$scenario['cycle']->id}/close")->assertConflict()
        ->assertJsonPath('code', 'cycle.requests_pending')
        ->assertJsonPath('meta.open_requests.0.status', 'under_committee_assessment');
});

it('erases the committee work of open requests on permanent deletion', function (): void {
    $scenario = committeeScenario(1, 1);
    $assessment = $scenario['requests'][0];
    submitCommitteeEvaluation($scenario['assessors'][0], $assessment, $scenario['version'])->assertOk();
    $entity = $assessment->entity;
    softDeleteWithOpenRequests($entity, [$assessment->request_number]);

    switchTo(superAdmin())->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'حذف نهائي بناءً على طلب الجهة الرسمي وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(Assessment::query()->whereKey($assessment->id)->exists())->toBeFalse()
        ->and(CommitteeAssignment::query()->count())->toBe(0)
        ->and(CommitteeScore::query()->count())->toBe(0)
        ->and(CommitteeAverage::query()->count())->toBe(0);
});

it('stores the committee entity data visibility in the assessment settings', function (): void {
    switchTo(systemAdmin())->getJson('/api/v1/settings/assessment')->assertOk()->assertJsonPath('data.committee_entity_data_visibility', 'name_only');
    test()->putJson('/api/v1/settings/assessment', ['committee_entity_data_visibility' => 'everything'])->assertUnprocessable();
    test()->putJson('/api/v1/settings/assessment', ['committee_entity_data_visibility' => 'code_only'])->assertOk()->assertJsonPath('data.committee_entity_data_visibility', 'code_only');
    switchTo(awardSecretary())->putJson('/api/v1/settings/assessment', ['committee_entity_data_visibility' => 'full'])->assertForbidden();
});

it('lists the running and draft award cycles before a criteria publication and keeps or moves the draft cycles', function (): void {
    $version = publishedAccreditedCriteria();
    $running = openAwardCycle($version);
    $request = submittedAdvancedRequest($running, $version);
    $draft = draftAwardCycle($version, ['submission_start' => Date::now()->addMonths(6), 'submission_end' => Date::now()->addMonths(7), 'committee_start' => Date::now()->addMonths(8), 'committee_end' => Date::now()->addMonths(9), 'announcement_at' => Date::now()->addMonths(10)]);
    $nextDraftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    test()->getJson("/api/v1/criteria/versions/{$nextDraftId}/check")->assertOk()
        ->assertJsonPath('meta.award_cycles.running.id', $running->id)
        ->assertJsonPath('meta.award_cycles.running.open_requests', 1)
        ->assertJsonPath('meta.award_cycles.requests_stay_on_cycle_version', true)
        ->assertJsonPath('meta.award_cycles.drafts.0.id', $draft->id);
    test()->postJson("/api/v1/criteria/versions/{$nextDraftId}/publish", ['confirm' => true, 'scope' => 'all', 'draft_cycles' => 'sometimes'])->assertUnprocessable();
    test()->postJson("/api/v1/criteria/versions/{$nextDraftId}/publish", ['confirm' => true, 'scope' => 'all', 'draft_cycles' => 'move'])->assertOk()
        ->assertJsonPath('meta.award_cycles.draft_cycles', 'move')
        ->assertJsonPath('meta.award_cycles.moved_cycle_ids', [$draft->id])
        ->assertJsonPath('meta.award_cycles.drafts.0.criteria_version_id', $nextDraftId);

    expect(AwardCycle::query()->findOrFail($running->id)->criteria_version_id)->toBe($version->id)
        ->and($request->refresh()->criteria_version_id)->toBe($version->id)
        ->and(Audit::query()->where('event', AuditEvent::CycleCriteriaVersionMoved->value)->count())->toBe(1);

    $thirdId = test()->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');
    test()->postJson("/api/v1/criteria/versions/{$thirdId}/publish", ['confirm' => true, 'scope' => 'new_only'])->assertOk()
        ->assertJsonPath('meta.award_cycles.draft_cycles', 'keep')
        ->assertJsonPath('meta.award_cycles.moved_cycle_ids', [])
        ->assertJsonPath('meta.award_cycles.drafts.0.criteria_version_id', $nextDraftId);
});

it('uses the default qualification threshold of sixty percent for a new cycle', function (): void {
    $version = publishedAccreditedCriteria();
    $region = regionWithCity()->region;

    switchTo(systemAdmin())->postJson('/api/v1/award/cycles', cyclePayload($version, $region))->assertCreated()->assertJsonPath('data.conditions.disqualification_score', '60.00');
});
