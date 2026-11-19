<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function weightsDraft(): CriteriaVersion
{
    publishedSelfCriteria();
    $draftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    return CriteriaVersion::query()->findOrFail($draftId);
}

/**
 * @param  array<string, string>  $weightsByCode
 * @return array{weights: list<array{id: int, weight: string}>}
 */
function weightsPayload(CriteriaVersion $version, array $weightsByCode): array
{
    $weights = [];

    foreach ($weightsByCode as $code => $weight) {
        $node = substr_count($code, '.') === 2 ? pointOf($version, $code) : subOf($version, $code);
        $weights[] = ['id' => $node->id, 'weight' => $weight];
    }

    return ['weights' => $weights];
}

function subsWeightsPath(MainCriterion $main): string
{
    return "/api/v1/criteria/mains/{$main->id}/subs/weights";
}

function pointsWeightsPath(SubCriterion $sub): string
{
    return "/api/v1/criteria/subs/{$sub->id}/points/weights";
}

it('saves the weights of several sub-criteria in one step and audits each change', function (): void {
    $draft = weightsDraft();

    test()->putJson(subsWeightsPath(mainOf($draft, 'M1')), weightsPayload($draft, ['M1.1' => '550', 'M1.2' => '450.00']))->assertOk()
        ->assertJsonPath('data.0.code', 'M1.1')
        ->assertJsonPath('data.0.weight', '550.00')
        ->assertJsonPath('data.1.weight', '450.00');

    expect(Audit::query()->where('event', AuditEvent::CriteriaSubUpdated->value)->count())->toBe(2);
});

it('rejects the whole set when the active sub-criteria would exceed the main weight', function (): void {
    $draft = weightsDraft();

    test()->putJson(subsWeightsPath(mainOf($draft, 'M1')), weightsPayload($draft, ['M1.1' => '700', 'M1.2' => '400']))->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weights_exceed_parent')
        ->assertJsonPath('meta.total', '1100.00')
        ->assertJsonPath('meta.capacity', '1000.00')
        ->assertJsonValidationErrors('weights');

    expect(subOf($draft, 'M1.1')->weight->toDecimal())->toBe('600.00');
});

it('rejects a sub-criterion of another main criterion and a guiding-points sub below its points', function (): void {
    $draft = weightsDraft();
    $main = mainOf($draft, 'M1');

    test()->putJson(subsWeightsPath($main), weightsPayload($draft, ['M2.1' => '100']))->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.node_not_a_child')
        ->assertJsonValidationErrors('weights.0.id');
    test()->putJson(subsWeightsPath($main), weightsPayload($draft, ['M1.1' => '650', 'M1.2' => '350']))->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weight_below_allocated');

    expect(subOf($draft, 'M1.1')->weight->toDecimal())->toBe('600.00');
});

it('refuses manual weights while the parent distributes its weight equally', function (): void {
    $draft = weightsDraft();
    $main = mainOf($draft, 'M1');
    $main->forceFill(['distribute_equally' => true])->save();

    test()->putJson(subsWeightsPath($main), weightsPayload($draft, ['M1.1' => '500']))->assertConflict()
        ->assertJsonPath('code', 'criteria.weights_distributed_equally');
});

it('saves the weights of the guiding points of a sub-criterion in one step', function (): void {
    $draft = weightsDraft();
    $sub = subOf($draft, 'M1.2');

    test()->putJson(pointsWeightsPath($sub), weightsPayload($draft, ['M1.2.1' => '150', 'M1.2.2' => '250']))->assertOk()
        ->assertJsonPath('data.0.weight', '150.00')
        ->assertJsonPath('data.1.weight', '250.00');
    test()->putJson(pointsWeightsPath($sub), weightsPayload($draft, ['M1.2.1' => '300']))->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.weights_exceed_parent');
    test()->putJson(pointsWeightsPath(subOf($draft, 'M1.1')), weightsPayload($draft, ['M1.2.1' => '100']))->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.sub_evaluated_directly');

    expect(Audit::query()->where('event', AuditEvent::CriteriaPointUpdated->value)->count())->toBe(2);
});

it('validates the weights and edits drafts only for criteria managers', function (): void {
    $draft = weightsDraft();
    $main = mainOf($draft, 'M1');
    $published = CriteriaVersion::query()->published()->firstOrFail();

    test()->putJson(subsWeightsPath($main), ['weights' => []])->assertUnprocessable()->assertJsonValidationErrors('weights');
    test()->putJson(subsWeightsPath($main), weightsPayload($draft, ['M1.1' => '0']))->assertUnprocessable()->assertJsonValidationErrors('weights.0.weight');
    test()->putJson(subsWeightsPath(mainOf($published, 'M1')), weightsPayload($published, ['M1.1' => '500']))->assertConflict()
        ->assertJsonPath('code', 'criteria.version_not_draft');
    switchTo(staffUser(RoleKey::RequestFollower))->putJson(subsWeightsPath($main), weightsPayload($draft, ['M1.1' => '500']))->assertForbidden();
});
