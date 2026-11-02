<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Notifications\Criteria\CriteriaNotification;
use App\Support\ValueObjects\Weight;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function subPayload(int $mainId, array $overrides = []): array
{
    return [
        'main_criterion_id' => $mainId,
        'name' => ['ar' => 'الشفافية والإفصاح', 'en' => 'Transparency and Disclosure'],
        'weight' => '200',
        'attachments' => [
            ['name' => ['ar' => 'تقرير الإفصاح السنوي', 'en' => 'Annual disclosure report'], 'required' => true],
            ['key' => 'board_minutes', 'name' => ['ar' => 'محاضر مجلس الإدارة', 'en' => 'Board meeting minutes'], 'required' => false],
        ],
        ...$overrides,
    ];
}

function openMain(): MainCriterion
{
    $draft = draftVersion();
    $main = mainIn($draft, 'M1', '1000.00');
    directSubIn($main, 'M1.1', '600.00');

    return $main;
}

it('creates a sub-criterion within the remaining weight with guiding points by default', function (): void {
    $main = openMain();
    $actor = criteriaOfficer();
    $colleague = criteriaOfficer();
    $administrator = systemAdmin();
    signIn($actor);

    $this->postJson('/api/v1/criteria/subs', subPayload($main->id))
        ->assertCreated()
        ->assertJsonPath('data.code', 'M1.2')
        ->assertJsonPath('data.main_criterion_id', $main->id)
        ->assertJsonPath('data.evaluation_type', 'guiding_points')
        ->assertJsonPath('data.has_cv', true)
        ->assertJsonPath('data.weight', '200.00')
        ->assertJsonPath('data.available_weight', '400.00')
        ->assertJsonPath('data.parent_name.en', 'Administrative Leadership')
        ->assertJsonPath('data.attachments.0.required', true)
        ->assertJsonPath('data.attachments.1.key', 'board_minutes')
        ->assertJsonPath('data.remaining_point_weight', '200.00');

    Notification::assertSentTo($colleague, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-03'
        && $notification->notice->link === '/admin/settings/criteria/'.$main->id.'/subs/'.SubCriterion::query()->where('code', 'M1.2')->value('id'));
    Notification::assertNotSentTo($administrator, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-03');
    expect(Audit::query()->where('event', AuditEvent::CriteriaSubCreated->value)->exists())->toBeTrue();
});

it('blocks a sub-criterion weight above the remaining weight of the main criterion', function (): void {
    $main = openMain();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs', subPayload($main->id, ['weight' => '400.01']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_exceeds_remaining')
        ->assertJsonPath('meta.remaining_weight', '400.00')
        ->assertJsonValidationErrors('weight');

    expect(SubCriterion::query()->where('main_criterion_id', $main->id)->count())->toBe(1);
});

it('allows an inactive sub-criterion beyond the remaining weight', function (): void {
    $main = openMain();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs', subPayload($main->id, ['weight' => '900', 'active' => false]))
        ->assertCreated()
        ->assertJsonPath('data.active', false);
});

it('requires a weight unless the main criterion distributes equally', function (): void {
    $main = openMain();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs', subPayload($main->id, ['weight' => null]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_required');

    $main->forceFill(['distribute_equally' => true])->save();

    $this->postJson('/api/v1/criteria/subs', subPayload($main->id, ['weight' => null]))
        ->assertCreated()
        ->assertJsonPath('data.weight', '500.00')
        ->assertJsonPath('data.available_weight', '500.00');
});

it('validates the sub-criterion payload', function (array $overrides, string $field): void {
    $main = openMain();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs', subPayload($main->id, $overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing main' => [['main_criterion_id' => null], 'main_criterion_id'],
    'unknown evaluation type' => [['evaluation_type' => 'guiding-points'], 'evaluation_type'],
    'attachment without a name' => [['attachments' => [['required' => true]]], 'attachments.0.name'],
    'attachment with an unknown field' => [['attachments' => [['name' => ['ar' => 'تقرير', 'en' => 'Report'], 'required' => true, 'size' => 10]]], 'attachments.0'],
    'invalid attachment key' => [['attachments' => [['key' => 'Board Minutes', 'name' => ['ar' => 'محاضر', 'en' => 'Minutes'], 'required' => true]]], 'attachments.0.key'],
    'negative weight' => [['weight' => '-5'], 'weight'],
]);

it('answers 404 for a main criterion that does not exist', function (): void {
    openMain();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs', subPayload(999999))->assertNotFound();
});

it('updates a sub-criterion and enforces the weight guards', function (): void {
    $main = openMain();
    $sub = subIn($main, 'M1.2', '300.00', 2);
    pointIn($sub, 'M1.2.1', '250.00');
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/subs/'.$sub->id, ['weight' => '450'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_exceeds_remaining')
        ->assertJsonPath('meta.remaining_weight', '400.00');
    $this->putJson('/api/v1/criteria/subs/'.$sub->id, ['weight' => '200'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_below_allocated');
    $this->putJson('/api/v1/criteria/subs/'.$sub->id, ['weight' => '400', 'has_cv' => false, 'assessor_note_label' => ['ar' => 'اكتب ملاحظتك عن مستوى الإفصاح', 'en' => 'Describe the disclosure level']])
        ->assertOk()
        ->assertJsonPath('data.weight', '400.00')
        ->assertJsonPath('data.has_cv', false)
        ->assertJsonPath('data.assessor_note_label.en', 'Describe the disclosure level')
        ->assertJsonPath('data.remaining_point_weight', '150.00');

    $audit = Audit::query()->where('event', AuditEvent::CriteriaSubUpdated->value)->firstOrFail();
    expect($audit->old_values['weight'] ?? null)->toBe('300.00')->and($audit->new_values['weight'] ?? null)->toBe('400.00');
});

it('changes the evaluation type only through the dedicated switch', function (): void {
    $sub = subIn(openMain(), 'M1.2', '300.00', 2);
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/subs/'.$sub->id, ['evaluation_type' => 'direct'])->assertUnprocessable()->assertJsonValidationErrors('evaluation_type');
    $this->putJson('/api/v1/criteria/subs/'.$sub->id, ['active' => false])->assertUnprocessable()->assertJsonValidationErrors('active');
});

it('warns about the guiding points before switching to direct evaluation', function (): void {
    $sub = subIn(openMain(), 'M1.2', '300.00', 2);
    pointIn($sub, 'M1.2.1', '100.00');
    pointIn($sub, 'M1.2.2', '100.00', 2);
    $colleague = criteriaOfficer();
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'direct'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.points_confirm_required')
        ->assertJsonPath('meta.impact.point_count', 2)
        ->assertJsonValidationErrors('confirm_delete_points');
    expect(GuidingPoint::query()->where('sub_criterion_id', $sub->id)->count())->toBe(2);

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'direct', 'confirm_delete_points' => true])
        ->assertOk()
        ->assertJsonPath('data.evaluation_type', 'direct')
        ->assertJsonPath('data.point_count', 0);

    $audit = Audit::query()->where('event', AuditEvent::CriteriaSubTypeSwitched->value)->firstOrFail();
    expect($audit->new_values['removed_point_codes'] ?? null)->toBe(['M1.2.1', 'M1.2.2']);
    Notification::assertSentTo($colleague, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-05');

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'guiding_points'])
        ->assertOk()
        ->assertJsonPath('data.evaluation_type', 'guiding_points');
    Notification::assertSentTo($colleague, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code->value === 'STD-04');
});

it('treats switching to the current evaluation type as a no-op', function (): void {
    $sub = subIn(openMain(), 'M1.2', '300.00', 2);
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'guiding_points'])->assertOk();
    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'scored'])->assertUnprocessable()->assertJsonValidationErrors('evaluation_type');

    expect(Audit::query()->where('event', AuditEvent::CriteriaSubTypeSwitched->value)->exists())->toBeFalse();
});

it('blocks activating a sub-criterion that no longer fits the main criterion', function (): void {
    $main = openMain();
    $sub = directSubIn($main, 'M1.2', '500.00', 2);
    $sub->forceFill(['is_active' => false])->save();
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/active', ['active' => true])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_exceeds_remaining');

    $sub->forceFill(['weight' => Weight::of('400.00')])->save();
    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/active', ['active' => true])->assertOk()->assertJsonPath('data.active', true);
});

it('rebalances an equally distributed main criterion when a sub-criterion is toggled', function (): void {
    $draft = draftVersion();
    $main = mainIn($draft, 'M1', '900.00');
    $main->forceFill(['distribute_equally' => true])->save();
    $first = directSubIn($main, 'M1.1', '300.00');
    $second = directSubIn($main, 'M1.2', '300.00', 2);
    directSubIn($main, 'M1.3', '300.00', 3);
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/subs/'.$second->id.'/active', ['active' => false])->assertOk();

    expect($first->refresh()->weight->toDecimal())->toBe('450.00');
});

it('reorders the sub-criteria of a main criterion', function (): void {
    $main = openMain();
    $first = SubCriterion::query()->where('main_criterion_id', $main->id)->firstOrFail();
    $second = directSubIn($main, 'M1.2', '400.00', 2);
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/mains/'.$main->id.'/subs/reorder', ['ordered_ids' => [$second->id, $first->id]])
        ->assertOk()
        ->assertJsonPath('data.0.code', 'M1.2')
        ->assertJsonPath('data.1.order', 2);
    $this->postJson('/api/v1/criteria/mains/'.$main->id.'/subs/reorder', ['ordered_ids' => [$second->id]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'order.incomplete');
});

it('removes a sub-criterion with its guiding points', function (): void {
    $main = openMain();
    $sub = subIn($main, 'M1.2', '300.00', 2);
    $point = pointIn($sub, 'M1.2.1', '100.00');
    signIn(criteriaOfficer());

    $this->deleteJson('/api/v1/criteria/subs/'.$sub->id)->assertNoContent();

    expect(SubCriterion::query()->whereKey($sub->id)->exists())->toBeFalse()
        ->and(GuidingPoint::query()->whereKey($point->id)->exists())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::CriteriaSubRemoved->value)->exists())->toBeTrue();
});

it('shows a sub-criterion with its guiding points', function (): void {
    $sub = subIn(openMain(), 'M1.2', '300.00', 2);
    pointIn($sub, 'M1.2.1', '100.00');
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/subs/'.$sub->id)
        ->assertOk()
        ->assertJsonPath('data.sub.code', 'M1.2')
        ->assertJsonPath('data.sub.allocated_point_weight', '100.00')
        ->assertJsonPath('data.sub.available_weight', '400.00')
        ->assertJsonPath('data.points.0.code', 'M1.2.1');
});

it('denies sub-criterion writes to roles without criteria.manage', function (RoleKey $role): void {
    $main = openMain();
    $sub = SubCriterion::query()->where('main_criterion_id', $main->id)->firstOrFail();
    signIn(staffUser($role));

    $this->postJson('/api/v1/criteria/subs', subPayload($main->id))->assertForbidden();
    $this->putJson('/api/v1/criteria/subs/'.$sub->id, ['weight' => '100'])->assertForbidden();
    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'direct'])->assertForbidden();
    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/active', ['active' => false])->assertForbidden();
    $this->deleteJson('/api/v1/criteria/subs/'.$sub->id)->assertForbidden();
})->with([
    'system administrator' => [RoleKey::SystemAdmin],
    'filtering officer' => [RoleKey::FilteringOfficer],
]);

it('denies reading sub-criteria to roles without criteria.view', function (): void {
    $sub = SubCriterion::query()->where('main_criterion_id', openMain()->id)->firstOrFail();
    signIn(staffUser(RoleKey::Assessor));

    $this->getJson('/api/v1/criteria/subs/'.$sub->id)->assertForbidden();
});
