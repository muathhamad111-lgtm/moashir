<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Assessments\AssessmentScore;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const OVERRIDE_REASON = 'طلبت الجهة تصحيح درجة أُدخلت بالخطأ عبر خطاب رسمي.';

beforeEach(function (): void {
    Notification::fake();
});

it('starts a self assessment draft bound to the published criteria version', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $year = Date::now('Asia/Riyadh')->year;

    signIn($owner)->postJson('/api/v1/assessments', ['path' => 'self'])
        ->assertCreated()
        ->assertJsonPath('data.number', sprintf('SLF-%d-000001', $year))
        ->assertJsonPath('data.path', 'self')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_open', true)
        ->assertJsonPath('data.is_frozen', false)
        ->assertJsonPath('data.criteria_version_id', $version->id)
        ->assertJsonPath('data.scale_kind', 'of_10')
        ->assertJsonPath('data.entity.reference', $entity->reference())
        ->assertJsonPath('data.result', null);

    $assessment = Assessment::query()->sole();
    $transition = AssessmentStatusTransition::query()->sole();

    expect($transition->from_status)->toBeNull()
        ->and($transition->to_status)->toBe('draft')
        ->and($transition->actor_user_id)->toBe($owner->id)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentCreated->value)->where('auditable_id', $assessment->id)->exists())->toBeTrue();

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::SelfAssessmentStarted
        && $notification->notice->link === '/assessment/self'
        && ! in_array('mail', $channels, true));
});

it('numbers self assessments sequentially per year', function (): void {
    publishedSelfCriteria();
    $year = Date::now('Asia/Riyadh')->year;

    startedSelfAssessment(ownerOf(entityWithOwner()));
    $second = startedSelfAssessment(ownerOf(entityWithOwner()));

    expect($second->request_number)->toBe(sprintf('SLF-%d-000002', $year));
});

it('keeps one open self draft per entity and allows a new one after submission', function (): void {
    $version = publishedSelfCriteria();
    $owner = ownerOf(entityWithOwner());
    $draft = startedSelfAssessment($owner);

    $this->postJson('/api/v1/assessments', ['path' => 'self'])
        ->assertConflict()
        ->assertJsonPath('code', 'self.draft_exists')
        ->assertJsonPath('meta.assessment_id', $draft->id);

    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();
    $this->postJson('/api/v1/assessments', ['path' => 'self'])->assertCreated();

    expect(Assessment::query()->where('entity_id', $owner->entity_id)->count())->toBe(2);
});

it('enforces one open draft per entity in the database', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    Assessment::factory()->forEntity($entity)->boundTo($version)->create();

    expect(fn () => Assessment::factory()->forEntity($entity)->boundTo($version)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('cannot start without a published criteria version', function (): void {
    signIn(ownerOf(entityWithOwner()))->postJson('/api/v1/assessments', ['path' => 'self'])
        ->assertNotFound()
        ->assertJsonPath('code', 'criteria.no_published_version');
});

it('refuses other assessment paths when starting', function (mixed $path): void {
    publishedSelfCriteria();

    signIn(ownerOf(entityWithOwner()))->postJson('/api/v1/assessments', ['path' => $path])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['path']);
})->with(['accredited', 'advanced', '', null]);

it('lets only entity accounts start a self assessment', function (RoleKey $role): void {
    publishedSelfCriteria();

    signIn(staffUser($role))->postJson('/api/v1/assessments', ['path' => 'self'])->assertForbidden();
})->with([RoleKey::SystemAdmin, RoleKey::RequestFollower, RoleKey::Assessor, RoleKey::CriteriaOfficer, RoleKey::FinanceOfficer]);

it('answers 403 to the Super Admin, who has no entity of its own', function (): void {
    publishedSelfCriteria();

    signIn(superAdmin())->postJson('/api/v1/assessments', ['path' => 'self'])
        ->assertForbidden()
        ->assertJsonPath('code', 'assessment.entity_required');
});

it('asks guests to sign in', function (): void {
    $this->postJson('/api/v1/assessments', ['path' => 'self'])->assertUnauthorized();
    $this->getJson('/api/v1/assessments')->assertUnauthorized();
});

it('saves the raw input next to the normalised percentage and versions every change', function (): void {
    $version = publishedSelfCriteria();
    $owner = ownerOf(entityWithOwner());
    $draft = startedSelfAssessment($owner);
    $direct = subOf($version, 'M1.1');

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '7.25', 'M1.2.1' => '9'])])
        ->assertOk()
        ->assertJsonPath('data.scale.kind', 'of_10')
        ->assertJsonPath('data.scale.maximum', '10.00')
        ->assertJsonPath("data.scores.subs.{$direct->id}.raw_value", '7.25')
        ->assertJsonPath("data.scores.subs.{$direct->id}.percent", '72.50')
        ->assertJsonPath('data.progress.scored', 2)
        ->assertJsonPath('data.progress.required', 4);

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '8', 'M1.2.1' => '9'])])->assertOk();

    $history = AssessmentScore::query()->where('sub_criterion_id', $direct->id)->orderBy('version')->get();
    expect($history)->toHaveCount(2)
        ->and($history[0]->raw_value)->toBe('7.25')
        ->and($history[0]->percent->toDecimal())->toBe('72.5000')
        ->and($history[0]->superseded_at)->not->toBeNull()
        ->and($history[1]->version)->toBe(2)
        ->and($history[1]->raw_value)->toBe('8.00')
        ->and($history[1]->superseded_at)->toBeNull()
        ->and(AssessmentScore::query()->where('guiding_point_id', pointOf($version, 'M1.2.1')->id)->count())->toBe(1);

    $audit = Audit::query()->where('event', AuditEvent::AssessmentScoresSaved->value)->orderByDesc('id')->firstOrFail();
    expect($audit->old_values)->toBe(['M1.1' => '7.25'])
        ->and($audit->new_values)->toBe(['M1.1' => '8.00']);
});

it('treats the draft body as the complete score sheet', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '7', 'M2.1' => '6'])])->assertOk();

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '7', 'M2.1' => null])])
        ->assertOk()
        ->assertJsonPath('data.progress.scored', 1);

    expect(AssessmentScore::query()->where('assessment_id', $draft->id)->current()->count())->toBe(1)
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->count())->toBe(2);
});

it('rejects invalid draft scores', function (string $code, string $raw): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, [$code => $raw])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scores.subs.'.subOf($version, $code)->id]);
})->with([
    'above the scale' => ['M1.1', '10.5'],
    'three decimals' => ['M1.1', '7.125'],
    'negative' => ['M2.1', '-1'],
    'not a number' => ['M2.1', 'جيد'],
]);

it('rejects score items that are not scorable in the bound version', function (): void {
    $version = publishedSelfCriteria();
    $foreign = subOf(balancedDraft(), 'M1.2');
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $scoredThroughPoints = subOf($version, 'M1.2');
    $inactive = subOf($version, 'M2.2');

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => ['subs' => [(string) $scoredThroughPoints->id => '5']]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'score.use_points')
        ->assertJsonValidationErrors(['scores.subs.'.$scoredThroughPoints->id]);

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => [
        'subs' => [(string) $inactive->id => '5', (string) $foreign->id => '5'],
    ]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'score.item_not_in_version')
        ->assertJsonValidationErrors(['scores.subs.'.$inactive->id, 'scores.subs.'.$foreign->id]);

    $this->putJson("/api/v1/assessments/{$draft->id}", [])->assertUnprocessable()->assertJsonValidationErrors(['scores']);

    expect(AssessmentScore::query()->count())->toBe(0);
});

it('submits the self assessment and computes the result in the same transaction', function (): void {
    $version = publishedSelfCriteria();
    $owner = ownerOf(entityWithOwner());
    $draft = startedSelfAssessment($owner);
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    $this->postJson("/api/v1/assessments/{$draft->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'result_available')
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.total', '1150.00')
        ->assertJsonPath('data.max_total', '1500.00')
        ->assertJsonPath('data.percent', '76.67')
        ->assertJsonPath('data.main.0.code', 'M1')
        ->assertJsonPath('data.main.0.earned', '750.00')
        ->assertJsonPath('data.main.0.percent', '75.00')
        ->assertJsonPath('data.sub.1.code', 'M1.2')
        ->assertJsonPath('data.sub.1.evaluation_type', 'guiding_points')
        ->assertJsonPath('data.sub.1.earned', '300.00')
        ->assertJsonPath('data.sub.0.raw_value', '7.50')
        ->assertJsonPath('data.points.1.raw_value', '5.00')
        ->assertJsonPath('data.points.1.percent', '50.00')
        ->assertJsonCount(3, 'data.sub')
        ->assertJsonCount(2, 'data.points');

    $assessment = $draft->refresh();
    $snapshot = AssessmentResultSnapshot::query()->sole();

    expect($assessment->status->value)->toBe('result_available')
        ->and($assessment->submitted_at)->not->toBeNull()
        ->and($assessment->completed_at)->not->toBeNull()
        ->and($assessment->current_result_snapshot_id)->toBe($snapshot->id)
        ->and($snapshot->total_score->toDecimal())->toBe('1150.00000000')
        ->and($snapshot->percent->toDecimal())->toBe('76.6667')
        ->and($snapshot->computed_by_user_id)->toBeNull()
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $draft->id)->orderBy('id')->pluck('to_status')->all())->toBe(['draft', 'submitted', 'result_available'])
        ->and(Audit::query()->whereIn('event', [AuditEvent::AssessmentSubmitted->value, AuditEvent::AssessmentResultComputed->value])->count())->toBe(2);

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::SelfAssessmentSubmitted
        && in_array('mail', $channels, true));
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::SelfAssessmentResultAvailable
        && ! in_array('mail', $channels, true)
        && str_contains($notification->notice->body->en, '76.67%'));
});

it('returns the criteria names with the result in both languages', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    $this->getJson("/api/v1/assessments/{$draft->id}/result")
        ->assertOk()
        ->assertJsonPath('data.main.0.name', mainOf($version, 'M1')->name->toArray())
        ->assertJsonPath('data.points.0.description', pointOf($version, 'M1.2.1')->description->toArray());
});

it('accepts the last scores with the submission', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));

    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])
        ->assertOk()
        ->assertJsonPath('data.percent', '76.67');
});

it('refuses to submit before every scorable item is scored', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $missingPoint = pointOf($version, 'M1.2.2');

    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, ['M1.1' => '7', 'M1.2.1' => '8', 'M2.1' => '9'])])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'scores.incomplete')
        ->assertJsonValidationErrors(['scores.points.'.$missingPoint->id])
        ->assertJsonPath('meta.missing.0', ['level' => 'point', 'id' => $missingPoint->id, 'code' => 'M1.2.2']);

    expect($draft->refresh()->status->value)->toBe('draft')
        ->and(AssessmentScore::query()->count())->toBe(0)
        ->and(AssessmentResultSnapshot::query()->count())->toBe(0);
});

it('rejects illegal transitions with 409 state.transition_illegal', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    $this->postJson("/api/v1/assessments/{$draft->id}/submit")
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal')
        ->assertJsonPath('errors.state', ['result_available'])
        ->assertJsonPath('errors.attempted', ['submit']);

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '1'])])
        ->assertConflict()
        ->assertJsonPath('errors.attempted', ['save_draft']);

    expect(AssessmentResultSnapshot::query()->count())->toBe(1);
});

it('answers "result not issued yet" before the submission', function (): void {
    publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));

    $this->getJson("/api/v1/assessments/{$draft->id}/result")
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $draft->id, 'number' => $draft->request_number, 'path' => 'self', 'status' => 'draft', 'is_frozen' => false, 'available' => false,
        ], 'meta' => [], 'errors' => null]);
});

it('serves the form with the bound criteria tree, the scale and the saved scores', function (): void {
    $version = publishedSelfCriteria(ScaleKind::Relative);
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $point = pointOf($version, 'M1.2.1');
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.2.1' => '87.45'])])->assertOk();

    $this->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.assessment.id', $draft->id)
        ->assertJsonPath('data.scale', ['kind' => 'relative', 'maximum' => '100.00'])
        ->assertJsonPath("data.scores.points.{$point->id}", ['raw_value' => '87.45', 'percent' => '87.45'])
        ->assertJsonPath('data.progress', ['scored' => 1, 'required' => 4])
        ->assertJsonPath('data.criteria.version_id', $version->id)
        ->assertJsonCount(2, 'data.criteria.mains')
        ->assertJsonCount(1, 'data.criteria.mains.1.subs');
});

it('validates draft scores against the relative scale of the assessment', function (): void {
    $version = publishedSelfCriteria(ScaleKind::Relative);
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $sub = subOf($version, 'M2.1');

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => ['subs' => [(string) $sub->id => '75']]])
        ->assertOk()
        ->assertJsonPath("data.scores.subs.{$sub->id}.percent", '75.00');

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => ['subs' => [(string) $sub->id => '100.01']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scores.subs.'.$sub->id]);
});

it('throttles the draft-saved notification', function (): void {
    $version = publishedSelfCriteria();
    $owner = ownerOf(entityWithOwner());
    $draft = startedSelfAssessment($owner);

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '5'])])->assertOk();
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '6'])])->assertOk();

    Notification::assertSentToTimes($owner, EntityNotification::class, 2);
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::SelfAssessmentDraftSaved);
});

it('hides an entity assessment from other entities', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo(ownerOf(entityWithOwner()));

    $this->getJson("/api/v1/assessments/{$draft->id}")->assertNotFound();
    $this->getJson("/api/v1/assessments/{$draft->id}/form")->assertNotFound();
    $this->getJson("/api/v1/assessments/{$draft->id}/result")->assertNotFound();
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '5'])])->assertNotFound();
    $this->postJson("/api/v1/assessments/{$draft->id}/submit")->assertNotFound();
});

it('lets the System Administrator and the Requests Follower read self assessments without changing them', function (RoleKey $role): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo(staffUser($role));

    $this->getJson("/api/v1/assessments/{$draft->id}")->assertOk()->assertJsonPath('data.number', $draft->request_number);
    $this->getJson("/api/v1/assessments/{$draft->id}/result")->assertOk()->assertJsonPath('data.available', false);
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '5'])])->assertForbidden();
    $this->postJson("/api/v1/assessments/{$draft->id}/submit")->assertForbidden();
})->with([RoleKey::SystemAdmin, RoleKey::RequestFollower]);

it('keeps self assessments away from staff outside the read scope', function (RoleKey $role): void {
    publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo(staffUser($role));

    $this->getJson("/api/v1/assessments/{$draft->id}")->assertStatus($role === RoleKey::Assessor ? 404 : 403);
    $this->getJson('/api/v1/assessments')->assertStatus($role === RoleKey::Assessor ? 200 : 403)->assertJsonMissing(['number' => $draft->request_number]);
})->with([RoleKey::Assessor, RoleKey::CriteriaOfficer, RoleKey::ContentOfficer, RoleKey::FinanceOfficer]);

it('requires a reason when the Super Admin changes the self assessment of an entity', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $sub = subOf($version, 'M1.1');
    $superAdmin = superAdmin();
    switchTo($superAdmin);

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '6'])])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required');

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '6']), 'reason' => OVERRIDE_REASON])->assertOk();

    expect(AssessmentScore::query()->where('sub_criterion_id', $sub->id)->current()->sole()->change_reason)->toBe(OVERRIDE_REASON)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentScoresSaved->value)->sole()->reason)->toBe(OVERRIDE_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('lists the own assessment history with results, newest first', function (): void {
    $version = publishedSelfCriteria();
    $owner = ownerOf(entityWithOwner());
    $first = startedSelfAssessment($owner);
    $this->postJson("/api/v1/assessments/{$first->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();
    $this->travel(1)->minutes();
    $second = startedSelfAssessment($owner);
    startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo($owner);

    $this->getJson('/api/v1/assessments?filter[path]=self')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.result', null)
        ->assertJsonPath('data.1.id', $first->id)
        ->assertJsonPath('data.1.result', ['total' => '1150.00', 'max_total' => '1500.00', 'percent' => '76.67'])
        ->assertJsonPath('meta.pagination.total', 2);

    $this->getJson('/api/v1/assessments?filter[status]=result_available')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/assessments?sort=created_at')->assertOk()->assertJsonPath('data.0.id', $first->id);
});

it('lists every self assessment for the global readers', function (): void {
    publishedSelfCriteria();
    startedSelfAssessment(ownerOf(entityWithOwner()));
    startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo(staffUser(RoleKey::RequestFollower));

    $this->getJson('/api/v1/assessments')->assertOk()->assertJsonCount(2, 'data');
});

it('validates the history filters', function (string $query): void {
    signIn(ownerOf(entityWithOwner()))->getJson('/api/v1/assessments?'.$query)->assertUnprocessable();
})->with(['filter[path]=bag', 'filter[status]=closed', 'sort=-number', 'per_page=500', 'filter[unknown]=1']);

it('refuses assessment writes while the entity is deactivated', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $draft = startedSelfAssessment($owner);
    $entity->forceFill(['status' => 'deactivated', 'deactivated_at' => now(), 'deactivation_reason' => 'توقف نشاط الجهة مؤقتًا بطلب من مجلس الإدارة.'])->save();
    $draft->unsetRelation('entity');

    switchTo($owner)->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '5'])])
        ->assertForbidden()
        ->assertJsonPath('code', 'assessment.entity_not_active');
    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])
        ->assertForbidden()
        ->assertJsonPath('code', 'assessment.entity_not_active');

    switchTo(ownerOf(entityWithOwner(static fn ($factory) => $factory->deactivated())))->postJson('/api/v1/assessments', ['path' => 'self'])
        ->assertForbidden()
        ->assertJsonPath('code', 'assessment.entity_not_active');
});
