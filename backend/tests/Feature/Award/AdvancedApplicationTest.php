<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Assessments\EvidenceFile;
use App\Models\Audit\Audit;
use App\Models\Entities\Region;
use App\Models\Files\StoredFile;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('shows the entity the running cycle and its eligibility', function (): void {
    $version = publishedAccreditedCriteria();
    $owner = ownerOf(entityWithOwner());

    switchTo($owner)->getJson('/api/v1/assessments/advanced/current')->assertOk()
        ->assertJsonPath('data.cycle', null)
        ->assertJsonPath('data.eligibility', ['eligible' => false, 'window_open' => false, 'conditions' => []])
        ->assertJsonPath('data.request', null);

    $cycle = openAwardCycle($version);
    $entity = cycleEntity($cycle, 2020);

    switchTo(ownerOf($entity))->getJson('/api/v1/assessments/advanced/current')->assertOk()
        ->assertJsonPath('data.cycle.id', $cycle->id)
        ->assertJsonPath('data.eligibility.eligible', true)
        ->assertJsonPath('data.eligibility.conditions.0', ['rule' => 'min_entity_age', 'passed' => true, 'required' => 3, 'actual' => 6])
        ->assertJsonPath('data.eligibility.conditions.1', ['rule' => 'region', 'passed' => true, 'required' => null, 'actual' => null]);

    switchTo(requestFollower())->getJson('/api/v1/assessments/advanced/current')->assertForbidden();
});

it('opens one draft per entity and cycle, bound to the cycle criteria and scale', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version, ['scale_kind' => 'relative']);
    $entity = cycleEntity($cycle);
    $owner = ownerOf($entity);

    $id = applyForCycle($owner, $cycle)->assertCreated()
        ->assertJsonPath('data.path', 'advanced')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.award_cycle_id', $cycle->id)
        ->assertJsonPath('data.scale_kind', 'relative')
        ->assertJsonPath('data.criteria_version_id', $version->id)
        ->json('data.id');
    applyForCycle($owner, $cycle)->assertOk()->assertJsonPath('data.id', $id);

    $draft = Assessment::query()->findOrFail($id);

    expect($draft->request_number)->toStartWith('ADV-2026-')
        ->and($draft->region_id)->toBe($entity->region_id)
        ->and(Audit::query()->where('event', AuditEvent::AwardApplicationCreated->value)->count())->toBe(1)
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $id)->count())->toBe(1);

    switchTo($owner)->getJson("/api/v1/assessments/{$id}/form")->assertOk()
        ->assertJsonPath('data.assessment.status', 'draft')
        ->assertJsonPath('data.missing.missing_attachments.0.attachment_key', POLICY_DOCUMENT_KEY)
        ->assertJsonStructure(['data' => ['criteria', 'cv', 'evidence', 'upload_limits']]);
});

it('records a conditions rejection once and tells the entity which condition failed', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $young = cycleEntity($cycle, 2025);
    $owner = ownerOf($young);

    applyForCycle($owner, $cycle)->assertUnprocessable()
        ->assertJsonPath('code', 'cycle.conditions_failed')
        ->assertJsonPath('meta.conditions.0.rule', 'min_entity_age')
        ->assertJsonPath('meta.conditions.0.actual', 1)
        ->assertJsonValidationErrors('conditions');
    applyForCycle($owner, $cycle)->assertUnprocessable();

    $rejected = Assessment::query()->where('entity_id', $young->id)->sole();

    expect($rejected->status->value)->toBe('rejected_conditions')
        ->and($rejected->condition_failure['failures'][0]['rule'])->toBe('min_entity_age')
        ->and(Audit::query()->where('event', AuditEvent::AwardApplicationRejected->value)->count())->toBe(1);
    Notification::assertSentToTimes($owner, EntityNotification::class, 1);
    expect(financeNoticeSent($owner, NotificationCode::AdvancedConditionsRejected))->toBeTrue();

    switchTo($owner)->getJson('/api/v1/assessments/advanced/current')->assertOk()
        ->assertJsonPath('data.eligibility.eligible', false)
        ->assertJsonPath('data.request.status', 'rejected_conditions')
        ->assertJsonPath('data.request.condition_failure.failures.0.required', 3);

    $young->forceFill(['founding_year' => 2010])->save();
    applyForCycle($owner, $cycle)->assertCreated()->assertJsonPath('data.status', 'draft');
});

it('refuses entities outside the regions of the cycle', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $outside = entityWithOwner();

    applyForCycle(ownerOf($outside), $cycle)->assertUnprocessable()
        ->assertJsonPath('code', 'cycle.conditions_failed')
        ->assertJsonPath('meta.conditions.0.rule', 'region');
});

it('accepts applications only while the submission period is open', function (): void {
    $version = publishedAccreditedCriteria();
    $draftCycle = draftAwardCycle($version);
    $owner = ownerOf(cycleEntity($draftCycle));

    applyForCycle($owner, $draftCycle)->assertConflict()->assertJsonPath('code', 'cycle.submission_closed');

    $closed = closeCycleSubmissions(openAwardCycle($version, [], $draftCycle->regions->firstOrFail()));
    applyForCycle($owner, $closed)->assertConflict()->assertJsonPath('code', 'cycle.submission_closed');
    switchTo($owner)->postJson('/api/v1/assessments/advanced', ['award_cycle_id' => 999999])->assertConflict();
    test()->postJson('/api/v1/assessments/advanced', [])->assertUnprocessable()->assertJsonValidationErrors('award_cycle_id');
});

it('refuses applications from staff and deactivated entities', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $regionId = $cycle->regions->firstOrFail()->id;
    $deactivated = entityWithOwner(static fn ($factory) => $factory->deactivated()->state(['region_id' => $regionId]));

    applyForCycle(requestFollower(), $cycle)->assertForbidden();
    applyForCycle(ownerOf($deactivated), $cycle)->assertForbidden()->assertJsonPath('code', 'assessment.entity_not_active');
});

it('submits a complete draft, notifies the entity and the followers and allows one request per cycle', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $follower = requestFollower();
    $draft = advancedDraft($cycle);
    $owner = ownerOf($draft->entity);

    switchTo($owner)->postJson("/api/v1/assessments/{$draft->id}/submit")->assertUnprocessable()->assertJsonPath('code', 'submission.incomplete');

    switchTo($owner)->putJson("/api/v1/assessments/{$draft->id}/cv", ['cv' => accreditedCv($version)])->assertOk();
    uploadEvidence($owner, $draft, $version)->assertCreated();
    switchTo($owner)->postJson("/api/v1/assessments/{$draft->id}/submit")->assertOk()
        ->assertJsonPath('data.status', 'awaiting_review_assignment')
        ->assertJsonPath('data.submitted_at', '2026-11-01T08:00:00+00:00');

    expect(financeNoticeSent($owner, NotificationCode::AdvancedSubmitted))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AdvancedSubmitted))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::AwardSubmitted->value)->count())->toBe(1);

    switchTo($owner)->putJson("/api/v1/assessments/{$draft->id}/cv", ['cv' => accreditedCv($version)])->assertConflict();
    applyForCycle($owner, $cycle)->assertConflict()->assertJsonPath('code', 'advanced.request_exists')->assertJsonPath('meta.assessment_id', $draft->id);
});

it('refuses draft changes and submission once the submission period has passed', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $draft = readyAdvancedDraft($cycle, $version);
    $owner = ownerOf($draft->entity);

    Date::setTestNow($cycle->submission_end->copy()->addMinute());

    switchTo($owner)->putJson("/api/v1/assessments/{$draft->id}/cv", ['cv' => accreditedCv($version)])->assertConflict()->assertJsonPath('code', 'cycle.submission_closed');
    test()->postJson("/api/v1/assessments/{$draft->id}/submit")->assertConflict()->assertJsonPath('code', 'cycle.submission_closed');
});

it('checks the entry conditions again at submission', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $draft = readyAdvancedDraft($cycle, $version);
    $cycle->forceFill(['min_entity_age_years' => 60])->save();

    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertUnprocessable()->assertJsonPath('code', 'cycle.conditions_failed');

    expect($draft->refresh()->status->value)->toBe('draft');
});

it('lets the entity delete its own draft with its content and keeps the audit trail', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $draft = readyAdvancedDraft($cycle, $version);
    $owner = ownerOf($draft->entity);
    $storedFileId = EvidenceFile::query()->where('assessment_id', $draft->id)->value('stored_file_id');

    switchTo(requestFollower())->deleteJson("/api/v1/assessments/{$draft->id}")->assertForbidden();
    switchTo(ownerOf(cycleEntity($cycle)))->deleteJson("/api/v1/assessments/{$draft->id}")->assertNotFound();

    switchTo($owner)->deleteJson("/api/v1/assessments/{$draft->id}")->assertNoContent();

    expect(Assessment::query()->whereKey($draft->id)->exists())->toBeFalse()
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $draft->id)->exists())->toBeFalse()
        ->and(EvidenceFile::query()->where('assessment_id', $draft->id)->exists())->toBeFalse()
        ->and(StoredFile::withTrashed()->findOrFail($storedFileId)->attachable_id)->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::AwardDraftDeleted->value)->where('auditable_id', $draft->id)->exists())->toBeTrue();

    applyForCycle($owner, $cycle)->assertCreated();
});

it('refuses to delete a submitted advanced request or a draft of another path', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $submitted = submittedAdvancedRequest($cycle, $version);
    $accredited = openedAccreditedRequest();

    switchTo(ownerOf($submitted->entity))->deleteJson("/api/v1/assessments/{$submitted->id}")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo(ownerOf($accredited->entity))->deleteJson("/api/v1/assessments/{$accredited->id}")->assertForbidden();

    expect(fn () => AssessmentStatusTransition::query()->where('assessment_id', $submitted->id)->delete())->toThrow(QueryException::class);
});

it('hides the filtering result from the entity until the results are announced', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = initiallyAssessedRequest($cycle, $version, $assessor);
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);
    closeCycleSubmissions($cycle);
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.result', null);

    switchTo($officer)->postJson("/api/v1/award/cycles/{$cycle->id}/filtering/{$assessment->id}/disqualify", ['reason' => DISQUALIFY_REASON])->assertOk();

    $history = switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.is_open', true)
        ->json('data.status_history');
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.status', 'under_review');
    switchTo($owner)->getJson('/api/v1/assessments?filter[path]=advanced')->assertOk()->assertJsonPath('data.0.status', 'under_review');
    switchTo($owner)->getJson('/api/v1/assessments?filter[status]=not_qualified')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo($assessor)->getJson('/api/v1/assessments?filter[status]=not_qualified')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'awaiting_filtering');
    switchTo($assessor)->getJson('/api/v1/requests/assigned?filter[tab]=completed')->assertOk()->assertJsonPath('data.0.status', 'awaiting_filtering');
    switchTo(requestFollower())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'not_qualified');

    expect(array_column($history, 'to'))->not->toContain('awaiting_filtering')->not->toContain('not_qualified');

    $cycle->forceFill(['announced_at' => Date::now()])->save();
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'not_qualified');

    expect(Region::query()->count())->toBeGreaterThan(0);
});
