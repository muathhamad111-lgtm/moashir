<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Assessments\AssessmentScore;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use App\Models\Entities\FrozenDeadline;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const FREEZE_OVERRIDE_REASON = 'مراجعة إدارية لمسودة جهة محذوفة بطلب رسمي.';

beforeEach(function (): void {
    Notification::fake();
});

it('lists the open self draft in the deletion impact of the entity', function (): void {
    publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = startedSelfAssessment(ownerOf($entity));

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/deletion-impact")
        ->assertOk()
        ->assertJsonPath('data.requires_confirmation', true)
        ->assertJsonPath('data.open_requests', [[
            'id' => $draft->id, 'number' => $draft->request_number, 'path' => 'self', 'path_label' => ['ar' => 'التقييم الذاتي', 'en' => 'Self assessment'], 'status' => 'draft', 'cycle_id' => null, 'is_frozen' => false,
        ]]);
});

it('does not list a self assessment whose result is available', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $assessment = startedSelfAssessment(ownerOf($entity));
    $this->postJson("/api/v1/assessments/{$assessment->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/deletion-impact")
        ->assertOk()
        ->assertJsonPath('data.open_requests', []);
});

it('requires the deleter to acknowledge the open self draft', function (): void {
    publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = startedSelfAssessment(ownerOf($entity));

    switchTo(systemAdmin())->deleteJson("/api/v1/entities/{$entity->id}", ['reason' => FREEZE_REASON])
        ->assertConflict()
        ->assertJsonPath('code', 'entity.open_requests_confirmation_required')
        ->assertJsonPath('meta.open_requests.0.number', $draft->request_number);

    softDeleteWithOpenRequests($entity, [$draft->request_number]);

    expect(Audit::query()->where('event', AuditEvent::EntitySoftDeleted->value)->sole()->new_values['frozen_request_numbers'])->toBe([$draft->request_number]);
});

it('freezes the self draft of a deleted entity: every write answers 409 entity.deleted', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $draft = startedSelfAssessment($owner);
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    $scores = selfScores($version, SELF_FULL_MARKS_OF_10);

    switchTo(superAdmin());
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => $scores, 'reason' => FREEZE_OVERRIDE_REASON])->assertConflict()->assertJsonPath('code', 'entity.deleted');
    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => $scores, 'reason' => FREEZE_OVERRIDE_REASON])->assertConflict()->assertJsonPath('code', 'entity.deleted');

    switchTo($owner);
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => $scores])->assertConflict()->assertJsonPath('code', 'entity.deleted');
    $this->postJson('/api/v1/assessments', ['path' => 'self'])->assertConflict()->assertJsonPath('code', 'entity.deleted');

    expect($draft->refresh()->status->value)->toBe('draft')
        ->and(AssessmentScore::query()->count())->toBe(0);
});

it('keeps frozen drafts out of the staff lists and the search, but shows them in the entity record', function (): void {
    publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = startedSelfAssessment(ownerOf($entity));
    $active = startedSelfAssessment(ownerOf(entityWithOwner()));
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    switchTo(staffUser(RoleKey::RequestFollower));

    $this->getJson('/api/v1/assessments')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->id);
    $this->getJson("/api/v1/assessments?filter[entity_id]={$entity->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $draft->id)
        ->assertJsonPath('data.0.is_frozen', true);
    $this->getJson('/api/v1/search?q='.urlencode($draft->request_number))->assertOk()->assertJsonPath('data.1.total', 0);
    $this->getJson("/api/v1/assessments/{$draft->id}")->assertOk()->assertJsonPath('data.is_frozen', true);
    $this->getJson("/api/v1/assessments/{$draft->id}/result")->assertOk()->assertJsonPath('data.is_frozen', true);
    $this->getJson("/api/v1/assessments/{$draft->id}/form")->assertOk()->assertJsonPath('data.assessment.is_frozen', true);
});

it('marks the open requests of a deleted entity as frozen in its deletion impact', function (): void {
    publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = startedSelfAssessment(ownerOf($entity));
    softDeleteWithOpenRequests($entity, [$draft->request_number]);

    $this->getJson("/api/v1/entities/{$entity->id}/deletion-impact")
        ->assertOk()
        ->assertJsonPath('data.open_requests.0.number', $draft->request_number)
        ->assertJsonPath('data.open_requests.0.is_frozen', true);
});

it('unfreezes the draft in the same status when the entity is restored', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $draft = startedSelfAssessment($owner);
    softDeleteWithOpenRequests($entity, [$draft->request_number]);

    $this->postJson("/api/v1/entities/{$entity->id}/restore", ['reason' => FREEZE_REASON])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::EntityRestored->value)->sole()->new_values['unfrozen_request_numbers'])->toBe([$draft->request_number]);

    switchTo($owner)->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])
        ->assertOk()
        ->assertJsonPath('data.status', 'result_available');
});

it('pauses running deadlines on deletion and resumes them with the remaining time on restore', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    Date::setTestNow('2026-11-01 09:00:00');
    $assessment = Assessment::factory()->forEntity($entity)->boundTo($version)->create(['due_at' => Date::parse('2026-11-04 09:00:00')]);

    softDeleteWithOpenRequests($entity, [$assessment->request_number]);

    $frozen = FrozenDeadline::query()->sole();
    expect($frozen->subject_id)->toBe($assessment->id)
        ->and($frozen->attribute)->toBe('due_at')
        ->and($frozen->remaining_seconds)->toBe(3 * 86_400)
        ->and(Audit::query()->where('event', AuditEvent::EntitySoftDeleted->value)->sole()->new_values['paused_deadlines'])->toBe(1);

    Date::setTestNow('2026-11-11 15:30:00');
    switchTo(systemAdmin())->postJson("/api/v1/entities/{$entity->id}/restore", ['reason' => FREEZE_REASON])->assertOk();

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe(Date::parse('2026-11-14 15:30:00')->toIso8601String())
        ->and(FrozenDeadline::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::EntityRestored->value)->sole()->new_values['resumed_deadlines'])->toBe(1);
});

it('keeps an overdue deadline overdue by the same time after the restore', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    Date::setTestNow('2026-11-05 09:00:00');
    $assessment = Assessment::factory()->forEntity($entity)->boundTo($version)->create(['due_at' => Date::parse('2026-11-04 09:00:00')]);
    softDeleteWithOpenRequests($entity, [$assessment->request_number]);

    Date::setTestNow('2026-11-20 09:00:00');
    switchTo(systemAdmin())->postJson("/api/v1/entities/{$entity->id}/restore", ['reason' => FREEZE_REASON])->assertOk();

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe(Date::parse('2026-11-19 09:00:00')->toIso8601String());
});

it('deletes the self assessments with their scores, history and results on permanent deletion', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $completed = startedSelfAssessment($owner);
    $this->postJson("/api/v1/assessments/{$completed->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();
    $draft = startedSelfAssessment($owner);
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '6'])])->assertOk();
    $other = startedSelfAssessment(ownerOf(entityWithOwner()));
    softDeleteWithOpenRequests($entity, [$draft->request_number]);

    switchTo(superAdmin())->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'حذف نهائي بناءً على طلب الجهة الرسمي وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(Assessment::query()->pluck('id')->all())->toBe([$other->id])
        ->and(AssessmentScore::query()->count())->toBe(0)
        ->and(AssessmentResultSnapshot::query()->count())->toBe(0)
        ->and(AssessmentStatusTransition::query()->where('assessment_id', '!=', $other->id)->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCreated->value)->count())->toBe(3);
});
