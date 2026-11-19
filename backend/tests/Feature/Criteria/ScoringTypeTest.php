<?php

declare(strict_types=1);

use App\Enums\Criteria\ScoringType;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function scoringMainPayload(array $overrides = []): array
{
    return [
        'name' => ['ar' => 'الابتكار وإدارة المعرفة', 'en' => 'Innovation and Knowledge Management'],
        'weight' => '600',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function scoringSubPayload(int $mainId, array $overrides = []): array
{
    return [
        'main_criterion_id' => $mainId,
        'name' => ['ar' => 'الشفافية والإفصاح', 'en' => 'Transparency and Disclosure'],
        'weight' => '200',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function scoringPointPayload(array $overrides = []): array
{
    return [
        'description' => ['ar' => 'وجود خطة استراتيجية معتمدة.', 'en' => 'An approved strategic plan exists.'],
        'weight' => '100',
        ...$overrides,
    ];
}

it('gives a new main criterion the platform scale as the default type for its sub-criteria', function (): void {
    balancedDraft();
    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', ['scale_kind' => 'relative'])->assertOk();

    switchTo(criteriaOfficer())->postJson('/api/v1/criteria/mains', scoringMainPayload())
        ->assertCreated()
        ->assertJsonPath('data.default_scoring_type', 'relative');
    $this->postJson('/api/v1/criteria/mains', scoringMainPayload(['default_scoring_type' => 'achievement']))
        ->assertCreated()
        ->assertJsonPath('data.default_scoring_type', 'achievement');
});

it('passes the defaults down: a sub-criterion takes its main default, an indicator its sub-criterion default', function (): void {
    $main = mainIn(draftVersion(), 'M1', '1000.00');
    $main->forceFill(['default_scoring_type' => ScoringType::Achievement])->save();
    signIn(criteriaOfficer());

    $subId = $this->postJson('/api/v1/criteria/subs', scoringSubPayload($main->id))
        ->assertCreated()
        ->assertJsonPath('data.scoring_type', 'achievement')
        ->assertJsonPath('data.default_point_scoring_type', 'achievement')
        ->json('data.id');
    $this->postJson('/api/v1/criteria/subs', scoringSubPayload($main->id, ['scoring_type' => 'relative', 'default_point_scoring_type' => 'score']))
        ->assertCreated()
        ->assertJsonPath('data.scoring_type', 'relative')
        ->assertJsonPath('data.default_point_scoring_type', 'score');

    $this->postJson("/api/v1/criteria/subs/{$subId}/points", scoringPointPayload())
        ->assertCreated()
        ->assertJsonPath('data.point.scoring_type', 'achievement');
    $this->postJson("/api/v1/criteria/subs/{$subId}/points", scoringPointPayload(['scoring_type' => 'relative']))
        ->assertCreated()
        ->assertJsonPath('data.point.scoring_type', 'relative');
});

it('changes the types of an existing node and keeps them when the field is left out', function (): void {
    $draft = balancedDraft();
    $sub = subOf($draft, 'M1.1');
    $point = pointOf($draft, 'M1.1.1');
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/subs/'.$sub->id, ['default_point_scoring_type' => 'achievement'])->assertOk()
        ->assertJsonPath('data.default_point_scoring_type', 'achievement')
        ->assertJsonPath('data.scoring_type', 'score');
    $this->putJson('/api/v1/criteria/points/'.$point->id, ['scoring_type' => 'achievement'])->assertOk()
        ->assertJsonPath('data.point.scoring_type', 'achievement');
    $this->putJson('/api/v1/criteria/points/'.$point->id, ['weight' => '300'])->assertOk()
        ->assertJsonPath('data.point.scoring_type', 'achievement');
    $this->putJson('/api/v1/criteria/mains/'.mainOf($draft, 'M1')->id, ['default_scoring_type' => 'relative'])->assertOk()
        ->assertJsonPath('data.default_scoring_type', 'relative');

    expect(GuidingPoint::query()->findOrFail($point->id)->scoring_type)->toBe(ScoringType::Achievement);
});

it('refuses an unknown scoring type', function (string $endpoint, string $field): void {
    $draft = balancedDraft();
    $ids = ['main' => mainOf($draft, 'M1')->id, 'sub' => subOf($draft, 'M1.1')->id, 'point' => pointOf($draft, 'M1.1.1')->id];
    signIn(criteriaOfficer());

    $this->putJson(sprintf($endpoint, ...array_values($ids)), [$field => 'out_of_5'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'main default' => ['/api/v1/criteria/mains/%1$d', 'default_scoring_type'],
    'sub type' => ['/api/v1/criteria/subs/%2$d', 'scoring_type'],
    'sub default for indicators' => ['/api/v1/criteria/subs/%2$d', 'default_point_scoring_type'],
    'indicator type' => ['/api/v1/criteria/points/%3$d', 'scoring_type'],
]);

it('keeps the types when a version is cloned and counts a type change as score-affecting', function (): void {
    publishVersion(balancedDraft());
    $draftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');
    $draft = CriteriaVersion::query()->findOrFail($draftId);
    $point = pointOf($draft, 'M1.1.1');

    expect($point->scoring_type)->toBe(ScoringType::Score);
    $this->putJson('/api/v1/criteria/points/'.$point->id, ['scoring_type' => 'achievement'])->assertOk();

    $this->getJson("/api/v1/criteria/versions/{$draft->id}/changes")->assertOk()
        ->assertJsonPath('data.change_count', 1)
        ->assertJsonPath('data.score_affecting_count', 1)
        ->assertJsonPath('data.changes.0.fields', ['scoring_type'])
        ->assertJsonPath('data.changes.0.after.scoring_type', 'achievement');
});

it('shows the types in the workspace tree and in the published tree read by assessments', function (): void {
    $published = publishVersion(balancedDraft());
    signIn(criteriaOfficer());

    $this->getJson("/api/v1/criteria/versions/{$published->id}/tree")->assertOk()
        ->assertJsonPath('data.mains.0.default_scoring_type', 'score')
        ->assertJsonPath('data.mains.0.subs.0.scoring_type', 'score')
        ->assertJsonPath('data.mains.0.subs.0.points.0.scoring_type', 'score');
    $this->getJson('/api/v1/assessments/criteria')->assertOk()
        ->assertJsonPath('data.mains.0.subs.0.scoring_type', 'score')
        ->assertJsonPath('data.mains.0.subs.0.points.0.scoring_type', 'score');

    expect(MainCriterion::query()->count())->toBe(1)->and(SubCriterion::query()->count())->toBe(2);
});
