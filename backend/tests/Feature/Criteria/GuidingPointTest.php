<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function pointPayload(array $overrides = []): array
{
    return [
        'description' => ['ar' => 'نشر تقرير سنوي معتمد عن الأداء المالي والإداري.', 'en' => 'An approved annual report on financial and administrative performance is published.'],
        'weight' => '150',
        'attachments' => [['name' => ['ar' => 'التقرير السنوي', 'en' => 'Annual report'], 'required' => true]],
        ...$overrides,
    ];
}

function scoredSub(string $weight = '400.00'): SubCriterion
{
    $main = mainIn(draftVersion(), 'M1', '1000.00');
    directSubIn($main, 'M1.1', '600.00');

    return subIn($main, 'M1.2', $weight, 2);
}

it('creates a guiding point and returns it with the updated sub-criterion', function (): void {
    $sub = scoredSub();
    pointIn($sub, 'M1.2.1', '100.00');
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload())
        ->assertCreated()
        ->assertJsonPath('data.point.code', 'M1.2.2')
        ->assertJsonPath('data.point.order', 2)
        ->assertJsonPath('data.point.weight', '150.00')
        ->assertJsonPath('data.point.attachments.0.name.en', 'Annual report')
        ->assertJsonPath('data.sub.allocated_point_weight', '250.00')
        ->assertJsonPath('data.sub.remaining_point_weight', '150.00');

    expect(Audit::query()->where('event', AuditEvent::CriteriaPointCreated->value)->exists())->toBeTrue();
});

it('blocks a guiding point above the remaining weight of the sub-criterion', function (): void {
    $sub = scoredSub();
    pointIn($sub, 'M1.2.1', '300.00');
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload(['weight' => '100.01']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_exceeds_remaining')
        ->assertJsonPath('meta.remaining_weight', '100.00');
});

it('blocks adding a guiding point once the sub-criterion weight is exhausted', function (): void {
    $sub = scoredSub();
    pointIn($sub, 'M1.2.1', '400.00');
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload(['weight' => '0.01']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_exceeds_remaining')
        ->assertJsonPath('meta.remaining_weight', '0.00');
});

it('refuses guiding points on a directly evaluated sub-criterion', function (): void {
    $scored = scoredSub();
    $direct = SubCriterion::query()->where('main_criterion_id', $scored->main_criterion_id)->where('code', 'M1.1')->firstOrFail();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$direct->id.'/points', pointPayload())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.sub_evaluated_directly')
        ->assertJsonValidationErrors('evaluation_type');
});

it('distributes the sub-criterion weight equally over its active guiding points', function (): void {
    $sub = scoredSub();
    $sub->forceFill(['distribute_equally' => true])->save();
    signIn(criteriaOfficer());

    foreach (range(1, 3) as $ignored) {
        $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload(['weight' => null]))->assertCreated();
    }

    expect(GuidingPoint::query()->where('sub_criterion_id', $sub->id)->orderBy('sort_order')->get()->map(static fn (GuidingPoint $point): string => $point->weight->toDecimal())->all())
        ->toBe(['133.34', '133.33', '133.33']);
});

it('refuses an equal distribution that would produce a zero share', function (): void {
    $sub = scoredSub('0.02');
    $sub->forceFill(['distribute_equally' => true])->save();
    pointIn($sub, 'M1.2.1', '0.01');
    pointIn($sub, 'M1.2.2', '0.01', 2);
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload(['weight' => null]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_too_small_to_distribute');
});

it('validates the guiding point payload', function (array $overrides, string $field): void {
    $sub = scoredSub();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing description' => [['description' => null], 'description'],
    'one-language description' => [['description' => ['en' => 'Annual report']], 'description.ar'],
    'too many decimals' => [['weight' => '1.001'], 'weight'],
    'too many attachments' => [['attachments' => array_fill(0, 21, ['name' => ['ar' => 'تقرير', 'en' => 'Report'], 'required' => false])], 'attachments'],
]);

it('updates a guiding point within the remaining weight', function (): void {
    $sub = scoredSub();
    $point = pointIn($sub, 'M1.2.1', '100.00');
    pointIn($sub, 'M1.2.2', '200.00', 2);
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/points/'.$point->id, ['weight' => '200.01'])
        ->assertUnprocessable()
        ->assertJsonPath('meta.remaining_weight', '200.00');
    $this->putJson('/api/v1/criteria/points/'.$point->id, ['weight' => '200', 'attachments' => []])
        ->assertOk()
        ->assertJsonPath('data.point.weight', '200.00')
        ->assertJsonPath('data.point.attachments', [])
        ->assertJsonPath('data.sub.remaining_point_weight', '0.00');
    $this->putJson('/api/v1/criteria/points/'.$point->id, ['active' => false])->assertUnprocessable()->assertJsonValidationErrors('active');
});

it('toggles a guiding point and guards its reactivation', function (): void {
    $sub = scoredSub();
    $point = pointIn($sub, 'M1.2.1', '300.00');
    pointIn($sub, 'M1.2.2', '100.00', 2);
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/points/'.$point->id.'/active', ['active' => false])->assertOk()->assertJsonPath('data.active', false);
    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload(['weight' => '250']))->assertCreated();
    $this->patchJson('/api/v1/criteria/points/'.$point->id.'/active', ['active' => true])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_exceeds_remaining');
});

it('reorders the guiding points of a sub-criterion', function (): void {
    $sub = scoredSub();
    $first = pointIn($sub, 'M1.2.1', '100.00');
    $second = pointIn($sub, 'M1.2.2', '100.00', 2);
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points/reorder', ['ordered_ids' => [$second->id, $first->id]])
        ->assertOk()
        ->assertJsonPath('data.0.code', 'M1.2.2')
        ->assertJsonPath('data.0.order', 1);
    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points/reorder', ['ordered_ids' => [$second->id, $first->id, 999]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'order.incomplete');
});

it('removes a guiding point and returns the sub-criterion with its remaining weight', function (): void {
    $sub = scoredSub();
    $removed = pointIn($sub, 'M1.2.1', '100.00');
    $kept = pointIn($sub, 'M1.2.2', '100.00', 2);
    signIn(criteriaOfficer());

    $this->deleteJson('/api/v1/criteria/points/'.$removed->id)
        ->assertOk()
        ->assertJsonPath('data.sub.point_count', 1)
        ->assertJsonPath('data.sub.remaining_point_weight', '300.00');

    expect($kept->refresh()->sort_order)->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::CriteriaPointRemoved->value)->exists())->toBeTrue();
});

it('denies guiding point writes to roles without criteria.manage', function (RoleKey $role): void {
    $sub = scoredSub();
    $point = pointIn($sub, 'M1.2.1', '100.00');
    signIn(staffUser($role));

    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points', pointPayload())->assertForbidden();
    $this->putJson('/api/v1/criteria/points/'.$point->id, ['weight' => '50'])->assertForbidden();
    $this->patchJson('/api/v1/criteria/points/'.$point->id.'/active', ['active' => false])->assertForbidden();
    $this->postJson('/api/v1/criteria/subs/'.$sub->id.'/points/reorder', ['ordered_ids' => [$point->id]])->assertForbidden();
    $this->deleteJson('/api/v1/criteria/points/'.$point->id)->assertForbidden();
})->with([
    'system administrator' => [RoleKey::SystemAdmin],
    'assessor' => [RoleKey::Assessor],
]);
