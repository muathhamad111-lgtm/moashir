<?php

declare(strict_types=1);

use App\Data\Assessments\CarriedScore;
use App\Data\Assessments\CriteriaRebind;
use App\Data\Assessments\ScoringMain;
use App\Data\Assessments\ScoringPoint;
use App\Data\Assessments\ScoringSub;
use App\Data\Assessments\ScoringTree;
use App\Data\Criteria\CriteriaEditEffect;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Enums\Criteria\EvaluationType;
use App\Models\Assessments\AssessmentScore;
use App\Services\Assessments\CriteriaRebindPlanner;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\Weight;

function rebindTree(int $offset): ScoringTree
{
    return new ScoringTree($offset, [
        new ScoringMain($offset + 1, 'M1', Weight::of('1000.00'), [
            new ScoringSub($offset + 11, 'M1.1', Weight::of('600.00'), EvaluationType::Direct),
            new ScoringSub($offset + 12, 'M1.2', Weight::of('400.00'), EvaluationType::GuidingPoints, [
                new ScoringPoint($offset + 121, 'M1.2.1', Weight::of('200.00')),
                new ScoringPoint($offset + 122, 'M1.2.2', Weight::of('200.00')),
            ]),
        ]),
    ]);
}

function inMemoryScore(ScoreLevel $level, int $nodeId): AssessmentScore
{
    return (new AssessmentScore)->forceFill([
        'level' => $level,
        'sub_criterion_id' => $level === ScoreLevel::Sub ? $nodeId : null,
        'guiding_point_id' => $level === ScoreLevel::Point ? $nodeId : null,
        'raw_value' => '7.00',
        'percent' => Percent::of('70'),
    ]);
}

function rebindWith(CriteriaEditScope $scope, array $rescoredCodes): CriteriaRebind
{
    return new CriteriaRebind(rebindTree(1000), rebindTree(2000), new CriteriaEditEffect(CriteriaEditKind::Structural, $scope), $rescoredCodes);
}

/**
 * @return list<AssessmentScore>
 */
function scoredRows(): array
{
    return [inMemoryScore(ScoreLevel::Sub, 1011), inMemoryScore(ScoreLevel::Point, 1121), inMemoryScore(ScoreLevel::Point, 1122)];
}

it('carries the scores of unchanged items to the matching codes of the new version under scope all', function (): void {
    $plan = (new CriteriaRebindPlanner)->plan(scoredRows(), rebindWith(CriteriaEditScope::All, ['M1.1']));

    expect($plan->carriedCodes())->toBe(['M1.2.1', 'M1.2.2'])
        ->and(array_map(static fn (CarriedScore $carried): int => $carried->target->id, $plan->carried))->toBe([2121, 2122])
        ->and($plan->cleared)->toHaveCount(1)
        ->and($plan->cleared[0]->sub_criterion_id)->toBe(1011);
});

it('clears every score under scope reset_open', function (): void {
    $plan = (new CriteriaRebindPlanner)->plan(scoredRows(), rebindWith(CriteriaEditScope::ResetOpen, []));

    expect($plan->carried)->toBe([])
        ->and($plan->cleared)->toHaveCount(3);
});

it('clears items that no longer exist or changed level in the new version', function (): void {
    $next = new ScoringTree(2000, [
        new ScoringMain(2001, 'M1', Weight::of('1000.00'), [
            new ScoringSub(2011, 'M1.1', Weight::of('600.00'), EvaluationType::Direct),
            new ScoringSub(2013, 'M1.2.1', Weight::of('400.00'), EvaluationType::Direct),
        ]),
    ]);
    $rebind = new CriteriaRebind(rebindTree(1000), $next, new CriteriaEditEffect(CriteriaEditKind::Structural, CriteriaEditScope::All), []);

    $plan = (new CriteriaRebindPlanner)->plan(scoredRows(), $rebind);

    expect($plan->carriedCodes())->toBe(['M1.1'])
        ->and($plan->cleared)->toHaveCount(2);
});
