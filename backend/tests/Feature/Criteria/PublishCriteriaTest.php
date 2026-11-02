<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\CriteriaEditApplied;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaEditEvent;
use App\Models\Criteria\CriteriaVersion;
use App\Notifications\Criteria\CriteriaNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Services\Criteria\PublishedCriteriaTree;
use App\Support\ValueObjects\Weight;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function clonedDraft(): CriteriaVersion
{
    installedSeedCriteria();
    signIn(criteriaOfficer());
    $id = test()->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    return CriteriaVersion::query()->findOrFail($id);
}

function moveWeightBetweenSubs(CriteriaVersion $draft): void
{
    subOf($draft, 'M2.1')->forceFill(['weight' => Weight::of('450.00')])->save();
    subOf($draft, 'M2.2')->forceFill(['weight' => Weight::of('450.00')])->save();
    pointOf($draft, 'M2.1.1')->forceFill(['weight' => Weight::of('225.00')])->save();
    pointOf($draft, 'M2.1.2')->forceFill(['weight' => Weight::of('225.00')])->save();
}

it('publishes the first version without a scope and copies the assessment scale', function (): void {
    $draft = balancedDraft();
    $actor = criteriaOfficer();
    signIn($actor);

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.scale_kind', 'of_10')
        ->assertJsonPath('meta.edit_event_id', null)
        ->assertJsonPath('meta.effect', null)
        ->assertJsonPath('meta.change_count', 0);

    expect($draft->refresh()->published_by_user_id)->toBe($actor->id)
        ->and(CriteriaEditEvent::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::CriteriaVersionPublished->value)->exists())->toBeTrue();
});

it('blocks publishing inconsistent weights and lists every offending node', function (): void {
    $draft = draftVersion();
    $main = mainIn($draft, 'M1', '1000.00');
    $scored = subIn($main, 'M1.1', '600.00');
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weights_inconsistent')
        ->assertJsonValidationErrors(['nodes.main.'.$main->id, 'nodes.sub.'.$scored->id])
        ->assertJsonPath('meta.problems.0.node_id', $main->id)
        ->assertJsonPath('meta.problems.0.problem', 'weights_mismatch')
        ->assertJsonPath('meta.problems.0.expected_weight', '1000.00')
        ->assertJsonPath('meta.problems.0.actual_weight', '600.00')
        ->assertJsonPath('meta.problems.1.node_id', $scored->id)
        ->assertJsonPath('meta.problems.1.problem', 'no_active_point');

    expect($draft->refresh()->status)->toBe(CriteriaVersionStatus::Draft);
});

it('blocks publishing a version without an active main criterion', function (): void {
    $draft = draftVersion();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nodes.version')
        ->assertJsonPath('meta.problems.0.problem', 'no_active_main');
});

it('blocks guiding points that exceed their sub-criterion at publish time', function (): void {
    $draft = balancedDraft();
    pointOf($draft, 'M1.1.2')->forceFill(['weight' => Weight::of('301.00')])->save();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])
        ->assertUnprocessable()
        ->assertJsonPath('meta.problems.0.problem', 'points_exceed_sub')
        ->assertJsonPath('meta.problems.0.code', 'M1.1');
});

it('requires a scope when a published version is replaced', function (): void {
    $draft = clonedDraft();

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.scope_required')
        ->assertJsonValidationErrors('scope');
    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true, 'scope' => 'everything'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scope');
});

it('requires an explicit confirmation', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', [])->assertUnprocessable()->assertJsonValidationErrors('confirm');
});

it('publishes a structural edit with scope all, archives the previous version and records the edit', function (): void {
    $draft = clonedDraft();
    $previous = CriteriaVersion::query()->whereKeyNot($draft->id)->firstOrFail();
    moveWeightBetweenSubs($draft);
    $administrator = systemAdmin();

    $response = $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true, 'scope' => 'all', 'reason' => 'إعادة توزيع أوزان التخطيط الاستراتيجي.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('meta.change_count', 4)
        ->assertJsonPath('meta.affected_open_count', 0)
        ->assertJsonPath('meta.effect.kind', 'structural')
        ->assertJsonPath('meta.effect.scope', 'all')
        ->assertJsonPath('meta.effect.rebinds_open_assessments', true)
        ->assertJsonPath('meta.effect.keeps_scores_of_unchanged_items', true)
        ->assertJsonPath('meta.effect.clears_all_open_scores', false)
        ->assertJsonPath('meta.effect.reaches_closed_assessments', false);

    $edit = CriteriaEditEvent::query()->findOrFail($response->json('meta.edit_event_id'));
    expect($previous->refresh()->status)->toBe(CriteriaVersionStatus::Archived)
        ->and($previous->archived_at)->not->toBeNull()
        ->and($edit->kind)->toBe(CriteriaEditKind::Structural)
        ->and($edit->scope)->toBe(CriteriaEditScope::All)
        ->and($edit->from_version_id)->toBe($previous->id)
        ->and($edit->to_version_id)->toBe($draft->id)
        ->and($edit->reason)->toBe('إعادة توزيع أوزان التخطيط الاستراتيجي.')
        ->and(collect($edit->changes)->pluck('code')->sort()->values()->all())->toBe(['M2.1', 'M2.1.1', 'M2.1.2', 'M2.2'])
        ->and(collect($edit->changes)->firstWhere('code', 'M2.1'))->toMatchArray([
            'change' => 'modified',
            'fields' => ['weight'],
            'before' => ['weight' => '500.00'],
            'after' => ['weight' => '450.00'],
            'score_affecting' => true,
        ]);

    $audit = Audit::query()->where('event', AuditEvent::CriteriaEditedWithScope->value)->firstOrFail();
    expect($audit->reason)->toBe('إعادة توزيع أوزان التخطيط الاستراتيجي.')
        ->and($audit->new_values['scope'] ?? null)->toBe('all')
        ->and($audit->new_values['change_count'] ?? null)->toBe(4);
    Notification::assertSentTo($administrator, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code === NotificationCode::CriteriaEditedWithScope
        && str_contains($notification->notice->body->en, 'all assessments'));
});

it('dispatches CriteriaEditApplied with the scope and the changed items for the Assessments domain', function (): void {
    Event::fake([CriteriaEditApplied::class]);
    $draft = clonedDraft();
    moveWeightBetweenSubs($draft);
    subOf($draft, 'M1.3')->forceFill(['name' => ['ar' => 'ثقافة التميز', 'en' => 'Excellence Culture']])->save();

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true, 'scope' => 'reset_open'])->assertOk();

    Event::assertDispatched(CriteriaEditApplied::class, static fn (CriteriaEditApplied $event): bool => $event->editEvent->scope === CriteriaEditScope::ResetOpen
        && $event->editEvent->to_version_id === $draft->id
        && $event->effect->clearsAllOpenScores()
        && $event->effect->notifiesAffectedEntitiesAndAssessors()
        && count($event->changes) === 5
        && $event->rescoredCodes() === ['M2.1', 'M2.2', 'M2.1.1', 'M2.1.2']);
});

it('reports the effect of every scope', function (string $scope, bool $rebinds, bool $keepsScores, bool $clearsScores): void {
    $draft = clonedDraft();
    moveWeightBetweenSubs($draft);

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true, 'scope' => $scope])
        ->assertOk()
        ->assertJsonPath('meta.effect.scope', $scope)
        ->assertJsonPath('meta.effect.rebinds_open_assessments', $rebinds)
        ->assertJsonPath('meta.effect.keeps_scores_of_unchanged_items', $keepsScores)
        ->assertJsonPath('meta.effect.clears_all_open_scores', $clearsScores);
})->with([
    'all' => ['all', true, true, false],
    'new requests only' => ['new_only', false, false, false],
    'reset open requests' => ['reset_open', true, false, true],
]);

it('requires a reason from the Super Admin and raises a security alert', function (): void {
    $published = installedSeedCriteria();
    $superAdmin = superAdmin();
    signIn($superAdmin);
    $draftId = $this->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/criteria/versions/'.$draftId.'/publish', ['confirm' => true, 'scope' => 'new_only'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required')
        ->assertJsonValidationErrors('reason');

    $this->postJson('/api/v1/criteria/versions/'.$draftId.'/publish', ['confirm' => true, 'scope' => 'new_only', 'reason' => 'تصحيح عاجل بطلب من إدارة الجائزة.'])->assertOk();

    expect($published->refresh()->status)->toBe(CriteriaVersionStatus::Archived);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class, static fn (SecurityAlertNotification $notification): bool => $notification->code() === NotificationCode::ReasonRequiredAction);
});

it('lets the system administrator publish', function (): void {
    $draft = balancedDraft();
    signIn(systemAdmin());

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])->assertOk();
});

it('denies publishing to roles without criteria.publish', function (RoleKey $role): void {
    $draft = balancedDraft();
    signIn(staffUser($role));

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])->assertForbidden();
    expect($draft->refresh()->status)->toBe(CriteriaVersionStatus::Draft);
})->with([
    'content officer' => [RoleKey::ContentOfficer],
    'assessor' => [RoleKey::Assessor],
    'award secretary' => [RoleKey::AwardSecretary],
]);

it('denies publishing to entity users', function (): void {
    $draft = balancedDraft();
    signIn(entityUser());

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true])->assertForbidden();
});

it('refuses to publish a version that is not a draft', function (): void {
    $published = publishVersion(balancedDraft());
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions/'.$published->id.'/publish', ['confirm' => true, 'scope' => 'all'])
        ->assertConflict()
        ->assertJsonPath('code', 'criteria.version_not_draft');
});

it('raises STD-06 when the published scale differs from the previous version', function (): void {
    $draft = clonedDraft();
    $administrator = systemAdmin();
    config()->set('moashir.assessment.scale_kind', 'relative');

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true, 'scope' => 'new_only'])
        ->assertOk()
        ->assertJsonPath('data.scale_kind', 'relative');

    Notification::assertSentTo($administrator, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code === NotificationCode::CriteriaScaleChanged);
});

it('refreshes the cached published tree after publishing', function (): void {
    $draft = clonedDraft();
    expect(app(PublishedCriteriaTree::class)->current()['version_number'] ?? null)->toBe(1)
        ->and(Cache::has(PublishedCriteriaTree::CACHE_KEY))->toBeTrue();

    $this->postJson('/api/v1/criteria/versions/'.$draft->id.'/publish', ['confirm' => true, 'scope' => 'new_only'])->assertOk();

    expect(Cache::has(PublishedCriteriaTree::CACHE_KEY))->toBeFalse()
        ->and(app(PublishedCriteriaTree::class)->current()['version_number'] ?? null)->toBe($draft->version_number);
});
