<?php

declare(strict_types=1);

use App\Enums\Criteria\ScaleKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentScore;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\ValueObjects\Weight;

const SELF_FULL_MARKS_OF_10 = ['M1.1' => '7.5', 'M1.2.1' => '10', 'M1.2.2' => '5', 'M2.1' => '8'];

const FREEZE_REASON = 'طلبت الجهة إغلاق حسابها بخطاب رسمي من مجلس الإدارة.';

function publishedSelfCriteria(ScaleKind $scale = ScaleKind::OutOfTen): CriteriaVersion
{
    $version = draftVersion();
    $leadership = mainIn($version, 'M1', '1000.00');
    directSubIn($leadership, 'M1.1', '600.00');
    $strategy = subIn($leadership, 'M1.2', '400.00', 2);
    pointIn($strategy, 'M1.2.1', '200.00');
    pointIn($strategy, 'M1.2.2', '200.00', 2);
    $results = mainIn($version, 'M2', '500.00', 2);
    directSubIn($results, 'M2.1', '500.00');
    directSubIn($results, 'M2.2', '100.00', 2)->forceFill(['is_active' => false])->save();

    return publishVersion($version->refresh(), $scale);
}

/**
 * @param  array<string, string|null>  $rawByCode
 * @return array{subs: array<string, string|null>, points: array<string, string|null>}
 */
function selfScores(CriteriaVersion $version, array $rawByCode): array
{
    $scores = ['subs' => [], 'points' => []];

    foreach ($rawByCode as $code => $raw) {
        $sub = SubCriterion::query()->whereHas('mainCriterion', static fn ($query) => $query->where('criteria_version_id', $version->id))->where('code', $code)->first();

        if ($sub instanceof SubCriterion) {
            $scores['subs'][(string) $sub->id] = $raw;

            continue;
        }

        $scores['points'][(string) pointOf($version, $code)->id] = $raw;
    }

    return $scores;
}

function startedSelfAssessment(User $owner): Assessment
{
    $id = switchTo($owner)->postJson('/api/v1/assessments', ['path' => 'self'])->assertCreated()->json('data.id');

    return Assessment::query()->findOrFail($id);
}

function scoredSelfDraft(CriteriaVersion $version, ?Entity $entity = null): Assessment
{
    $owner = ownerOf($entity ?? entityWithOwner());
    $draft = startedSelfAssessment($owner);
    test()->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    return $draft;
}

function republishCriteria(string $scope, Closure $editDraft): CriteriaVersion
{
    $draftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');
    $draft = CriteriaVersion::query()->findOrFail($draftId);
    $editDraft($draft);

    test()->postJson("/api/v1/criteria/versions/{$draft->id}/publish", ['confirm' => true, 'scope' => $scope])->assertOk();

    return $draft->refresh();
}

function republishWithNewSubWeights(string $scope): CriteriaVersion
{
    return republishCriteria($scope, static function (CriteriaVersion $draft): void {
        subOf($draft, 'M1.1')->forceFill(['weight' => Weight::of('500.00')])->save();
        subOf($draft, 'M1.2')->forceFill(['weight' => Weight::of('500.00')])->save();
    });
}

function republishWithSmallerResults(string $scope): CriteriaVersion
{
    return republishCriteria($scope, static function (CriteriaVersion $draft): void {
        subOf($draft, 'M2.1')->forceFill(['weight' => Weight::of('400.00')])->save();
        mainOf($draft, 'M2')->forceFill(['weight' => Weight::of('400.00')])->save();
    });
}

/**
 * @return list<string>
 */
function currentScoreCodes(Assessment $assessment, CriteriaVersion $version): array
{
    $subs = SubCriterion::query()->whereHas('mainCriterion', static fn ($query) => $query->where('criteria_version_id', $version->id))->pluck('code', 'id')->all();
    $points = GuidingPoint::query()->whereHas('subCriterion.mainCriterion', static fn ($query) => $query->where('criteria_version_id', $version->id))->pluck('code', 'id')->all();
    $codes = [];

    foreach (AssessmentScore::query()->where('assessment_id', $assessment->id)->current()->get() as $score) {
        $codes[] = $score->sub_criterion_id !== null ? ($subs[$score->sub_criterion_id] ?? 'foreign') : ($points[(int) $score->guiding_point_id] ?? 'foreign');
    }

    sort($codes);

    return $codes;
}

/**
 * @param  list<string>  $numbers
 */
function softDeleteWithOpenRequests(Entity $entity, array $numbers): void
{
    switchTo(systemAdmin())->deleteJson("/api/v1/entities/{$entity->id}", [
        'reason' => FREEZE_REASON,
        'confirm_open_requests' => true,
        'acknowledged_request_numbers' => $numbers,
    ])->assertNoContent();
}

function restoreEntity(Entity $entity): void
{
    switchTo(systemAdmin())->postJson("/api/v1/entities/{$entity->id}/restore", ['reason' => FREEZE_REASON])->assertOk();
}
