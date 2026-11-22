<?php

declare(strict_types=1);

use App\Data\Criteria\ConsistencyProblem;
use App\Enums\Audit\AuditEvent;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Services\Criteria\CriteriaConsistencyChecker;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Support\Facades\Notification;

const DIRECT_INDICATOR_EVIDENCE_KEY = 'policy_review_minutes';

beforeEach(function (): void {
    Notification::fake();
});

function directIndicatorPayload(array $overrides = []): array
{
    return [
        'description' => ['ar' => 'تراجع السياسة سنويًا ويُوثَّق ذلك في محضر', 'en' => 'The policy is reviewed every year and minuted'],
        'attachments' => [['name' => ['ar' => 'محضر المراجعة', 'en' => 'Review minutes'], 'required' => true]],
        ...$overrides,
    ];
}

function indicatorsDraftMain(): MainCriterion
{
    return mainIn(draftVersion(), 'M1', '1000.00');
}

function unweightedPointIn(SubCriterion $sub, string $code, int $order = 1): GuidingPoint
{
    return GuidingPoint::factory()->for($sub, 'subCriterion')->create(['code' => $code, 'sort_order' => $order, 'weight' => null]);
}

/**
 * The accredited test tree with one descriptive indicator under the directly evaluated M1.1, carrying a required
 * attachment of its own.
 */
function accreditedCriteriaWithDirectIndicator(): CriteriaVersion
{
    $version = draftAccreditedCriteria();
    $indicator = unweightedPointIn(subOf($version, 'M1.1'), 'M1.1.1');
    $indicator->attachmentRequirements()->create([
        'key' => DIRECT_INDICATOR_EVIDENCE_KEY,
        'name' => ['ar' => 'محضر مراجعة السياسة', 'en' => 'Policy review minutes'],
        'is_required' => true,
        'sort_order' => 1,
    ]);

    return publishVersion($version->refresh());
}

it('adds a descriptive indicator without a weight to a directly evaluated sub-criterion', function (): void {
    $direct = directSubIn(indicatorsDraftMain(), 'M1.1', '600.00');
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/subs/'.$direct->id.'/points', directIndicatorPayload(['weight' => '100.00']))
        ->assertCreated()
        ->assertJsonPath('data.point.code', 'M1.1.1')
        ->assertJsonPath('data.point.weight', null)
        ->assertJsonPath('data.point.scoring_type', $direct->default_point_scoring_type->value)
        ->assertJsonPath('data.point.attachments.0.name.en', 'Review minutes')
        ->assertJsonPath('data.sub.evaluation_type', 'direct')
        ->assertJsonPath('data.sub.point_count', 1);

    $point = GuidingPoint::query()->where('sub_criterion_id', $direct->id)->firstOrFail();
    $this->putJson('/api/v1/criteria/points/'.$point->id, ['weight' => '50.00', 'description' => ['ar' => 'نص معدّل', 'en' => 'Edited text']])
        ->assertOk()
        ->assertJsonPath('data.point.weight', null)
        ->assertJsonPath('data.point.description.en', 'Edited text');
    $this->patchJson('/api/v1/criteria/points/'.$point->id.'/active', ['active' => false])->assertOk()->assertJsonPath('data.active', false);
    $this->patchJson('/api/v1/criteria/points/'.$point->id.'/active', ['active' => true])->assertOk()->assertJsonPath('data.active', true);

    expect($point->refresh()->weight)->toBeNull();
});

it('keeps a directly evaluated sub-criterion with descriptive indicators consistent and publishable', function (): void {
    $version = balancedDraft();
    unweightedPointIn(subOf($version, 'M1.2'), 'M1.2.1');

    $report = app(CriteriaConsistencyChecker::class)->check(app(CriteriaTreeLoader::class)->load($version));

    expect($report->isConsistent())->toBeTrue();
});

it('still refuses indicator weights and scoring types on a directly evaluated sub-criterion', function (): void {
    $direct = directSubIn(indicatorsDraftMain(), 'M1.1', '600.00');
    $point = unweightedPointIn($direct, 'M1.1.1');
    signIn(criteriaOfficer());

    $this->putJson('/api/v1/criteria/subs/'.$direct->id.'/points/weights', ['weights' => [['id' => $point->id, 'weight' => '100']]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.sub_evaluated_directly');
    $this->putJson('/api/v1/criteria/subs/'.$direct->id.'/points/scoring-type', ['scoring_type' => 'achievement'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.sub_evaluated_directly');
});

it('keeps the indicators and their attachments when switching to direct evaluation, without a weight', function (): void {
    $sub = subIn(indicatorsDraftMain(), 'M1.2', '300.00', 2);
    $first = pointIn($sub, 'M1.2.1', '100.00');
    pointIn($sub, 'M1.2.2', '200.00', 2);
    $first->attachmentRequirements()->create(['key' => 'survey', 'name' => ['ar' => 'استبيان', 'en' => 'Survey'], 'is_required' => true, 'sort_order' => 1]);
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'direct'])
        ->assertOk()
        ->assertJsonPath('data.evaluation_type', 'direct')
        ->assertJsonPath('data.point_count', 2);

    expect(GuidingPoint::query()->where('sub_criterion_id', $sub->id)->pluck('weight')->all())->toBe([null, null])
        ->and($first->attachmentRequirements()->count())->toBe(1);

    $audit = Audit::query()->where('event', AuditEvent::CriteriaSubTypeSwitched->value)->firstOrFail();
    expect($audit->old_values['indicator_weights'] ?? null)->toBe(['M1.2.1' => '100.00', 'M1.2.2' => '200.00'])
        ->and($audit->new_values['evaluation_type'] ?? null)->toBe('direct')
        ->and($audit->new_values['indicator_weights'] ?? null)->toBe(['M1.2.1' => null, 'M1.2.2' => null]);
});

it('splits the weight equally over the indicators when switching back to indicator scoring', function (): void {
    $sub = directSubIn(indicatorsDraftMain(), 'M1.2', '300.00', 2);
    unweightedPointIn($sub, 'M1.2.1');
    unweightedPointIn($sub, 'M1.2.2', 2)->forceFill(['is_active' => false])->save();
    unweightedPointIn($sub, 'M1.2.3', 3);
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'guiding_points'])
        ->assertOk()
        ->assertJsonPath('data.evaluation_type', 'guiding_points')
        ->assertJsonPath('data.distribute_equally', true);

    $weights = GuidingPoint::query()->where('sub_criterion_id', $sub->id)->orderBy('sort_order')->get()
        ->map(static fn (GuidingPoint $point): ?string => $point->weight?->toDecimal())->all();

    $problems = app(CriteriaConsistencyChecker::class)->check(app(CriteriaTreeLoader::class)->load($sub->mainCriterion->criteriaVersion))->problems;

    expect($weights)->toBe(['150.00', '100.00', '150.00'])
        ->and(array_filter($problems, static fn (ConsistencyProblem $problem): bool => $problem->nodeId === $sub->id))->toBe([]);
});

it('switches back to indicator scoring with no indicators and leaves the split untouched', function (): void {
    $sub = directSubIn(indicatorsDraftMain(), 'M1.2', '300.00', 2);
    signIn(criteriaOfficer());

    $this->patchJson('/api/v1/criteria/subs/'.$sub->id.'/evaluation-type', ['evaluation_type' => 'guiding_points'])
        ->assertOk()
        ->assertJsonPath('data.distribute_equally', false)
        ->assertJsonPath('data.point_count', 0);
});

it('serves the descriptive indicators of a direct sub-criterion with their attachments in the request form', function (): void {
    $version = accreditedCriteriaWithDirectIndicator();
    $assessment = openedAccreditedRequest();

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/form")
        ->assertOk()
        ->assertJsonPath('data.criteria.mains.0.subs.0.evaluation_type', 'direct')
        ->assertJsonPath('data.criteria.mains.0.subs.0.points.0.code', 'M1.1.1')
        ->assertJsonPath('data.criteria.mains.0.subs.0.points.0.weight', null)
        ->assertJsonPath('data.criteria.mains.0.subs.0.points.0.attachments.0.key', DIRECT_INDICATOR_EVIDENCE_KEY);

    expect(subOf($version, 'M1.1')->evaluation_type->value)->toBe('direct');
});

it('requires the attachments of a direct sub-criterion indicator before the submission', function (): void {
    $version = accreditedCriteriaWithDirectIndicator();
    $assessment = readyAccreditedDraft($version);
    $owner = ownerOf($assessment->entity);
    $direct = subOf($version, 'M1.1');

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertUnprocessable()
        ->assertJsonPath('code', 'submission.incomplete')
        ->assertJsonValidationErrors(["attachments.{$direct->id}.".DIRECT_INDICATOR_EVIDENCE_KEY]);

    uploadEvidence($owner, $assessment, $version, 'M1.1', DIRECT_INDICATOR_EVIDENCE_KEY)->assertCreated();

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
});

it('scores a direct sub-criterion as one unit and refuses scores on its indicators', function (): void {
    $version = draftVersion();
    $leadership = mainIn($version, 'M1', '1000.00');
    $direct = directSubIn($leadership, 'M1.1', '1000.00');
    $indicator = unweightedPointIn($direct, 'M1.1.1');
    publishVersion($version->refresh());
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));

    $this->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.criteria.mains.0.subs.0.points.0.code', 'M1.1.1')
        ->assertJsonPath('data.progress', ['scored' => 0, 'required' => 1]);
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => ['subs' => [], 'points' => [(string) $indicator->id => '8']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["scores.points.{$indicator->id}"]);
    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => ['subs' => [(string) $direct->id => '8'], 'points' => []]])
        ->assertOk()
        ->assertJsonPath('data.percent', '80.00');
});

it('keeps the scores of a direct sub-criterion when only its descriptive indicators change', function (): void {
    publishedSelfCriteria();
    $owner = ownerOf(entityWithOwner());
    $draft = startedSelfAssessment($owner);
    $published = CriteriaVersion::query()->published()->firstOrFail();
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($published, ['M1.1' => '7.5'])])->assertOk();

    $next = republishCriteria('all', static function (CriteriaVersion $draftVersion): void {
        unweightedPointIn(subOf($draftVersion, 'M1.1'), 'M1.1.1');
    });

    switchTo($owner)->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.criteria.version_id', $next->id)
        ->assertJsonPath('data.scores.subs.'.subOf($next, 'M1.1')->id.'.raw_value', '7.50');
});

it('does not count descriptive indicator changes as score-affecting in the publish preview', function (): void {
    publishedSelfCriteria();
    $draftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');
    $draft = CriteriaVersion::query()->findOrFail($draftId);
    unweightedPointIn(subOf($draft, 'M1.1'), 'M1.1.1');

    test()->getJson("/api/v1/criteria/versions/{$draft->id}/changes")
        ->assertOk()
        ->assertJsonPath('data.change_count', 1)
        ->assertJsonPath('data.score_affecting_count', 0)
        ->assertJsonPath('data.changes.0.code', 'M1.1.1')
        ->assertJsonPath('data.changes.0.score_affecting', false);
});
