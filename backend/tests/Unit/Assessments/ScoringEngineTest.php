<?php

declare(strict_types=1);

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringMain;
use App\Data\Assessments\ScoringPoint;
use App\Data\Assessments\ScoringSub;
use App\Data\Assessments\ScoringTree;
use App\Enums\Criteria\EvaluationType;
use App\Enums\Criteria\ScoringType;
use App\Services\Assessments\ScoringEngine;
use App\Support\ValueObjects\RawScore;
use App\Support\ValueObjects\Weight;

function directSub(int $id, string $code, string $weight): ScoringSub
{
    return new ScoringSub($id, $code, Weight::of($weight), EvaluationType::Direct, ScoringType::Score);
}

/**
 * @param  list<array{int, string, string}>  $points
 */
function pointsSub(int $id, string $code, string $weight, array $points): ScoringSub
{
    return new ScoringSub($id, $code, Weight::of($weight), EvaluationType::GuidingPoints, ScoringType::Score, array_map(
        static fn (array $point): ScoringPoint => new ScoringPoint($point[0], $point[1], Weight::of($point[2]), ScoringType::Score),
        $points,
    ));
}

function sampleScoringTree(): ScoringTree
{
    return new ScoringTree(1, [
        new ScoringMain(10, 'M1', Weight::of('1000.00'), [
            directSub(11, 'M1.1', '600.00'),
            pointsSub(12, 'M1.2', '400.00', [[121, 'M1.2.1', '200.00'], [122, 'M1.2.2', '200.00']]),
        ]),
        new ScoringMain(20, 'M2', Weight::of('500.00'), [directSub(21, 'M2.1', '500.00')]),
    ]);
}

/**
 * @param  array<string, string>  $rawByCode
 */
function sheetFor(ScoringTree $tree, ScoringType $scale, array $rawByCode): ScoreSheet
{
    $entries = [];

    foreach ($tree->scorableUnits() as $unit) {
        if (isset($rawByCode[$unit->code])) {
            $entries[] = new ScoredUnit($unit, RawScore::on($scale, $rawByCode[$unit->code]));
        }
    }

    return ScoreSheet::of($entries);
}

it('aggregates point to sub to main to total on the out-of-ten scale', function (): void {
    $tree = sampleScoringTree();
    $result = (new ScoringEngine)->compute($tree, sheetFor($tree, ScoringType::Score, [
        'M1.1' => '7.5', 'M1.2.1' => '10', 'M1.2.2' => '5', 'M2.1' => '8',
    ]));

    expect($result->total->toDecimal())->toBe('1150.00000000')
        ->and($result->totalWeight->toDecimal())->toBe('1500.00')
        ->and($result->percent->toDecimal())->toBe('76.6667')
        ->and($result->percent->toDisplay())->toBe('76.67')
        ->and($result->mains[0]->earned->toDecimal())->toBe('750.00000000')
        ->and($result->mains[0]->percent->toDisplay())->toBe('75.00')
        ->and($result->mains[0]->subs[0]->percent->toDecimal())->toBe('75.0000')
        ->and($result->mains[0]->subs[0]->earned->toDisplay())->toBe('450.00')
        ->and($result->mains[0]->subs[1]->earned->toDisplay())->toBe('300.00')
        ->and($result->mains[0]->subs[1]->points[0]->percent->toDisplay())->toBe('100.00')
        ->and($result->mains[0]->subs[1]->points[1]->earned->toDisplay())->toBe('100.00')
        ->and($result->mains[1]->percent->toDisplay())->toBe('80.00');
});

it('gives the same result for the same marks on the relative scale', function (): void {
    $tree = sampleScoringTree();
    $engine = new ScoringEngine;

    $outOfTen = $engine->compute($tree, sheetFor($tree, ScoringType::Score, ['M1.1' => '7.5', 'M1.2.1' => '10', 'M1.2.2' => '5', 'M2.1' => '8']));
    $relative = $engine->compute($tree, sheetFor($tree, ScoringType::Relative, ['M1.1' => '75', 'M1.2.1' => '100', 'M1.2.2' => '50', 'M2.1' => '80']));

    expect($relative->total->toDecimal())->toBe($outOfTen->total->toDecimal())
        ->and($relative->percent->toDecimal())->toBe($outOfTen->percent->toDecimal())
        ->and($relative->mains[0]->subs[1]->earned->toDecimal())->toBe($outOfTen->mains[0]->subs[1]->earned->toDecimal())
        ->and($relative->mains[0]->subs[0]->raw?->toDecimal())->toBe('75.00')
        ->and($outOfTen->mains[0]->subs[0]->raw?->toDecimal())->toBe('7.50');
});

it('keeps the raw input next to the normalised percentage', function (ScoringType $scale, string $raw, string $percent): void {
    expect(RawScore::on($scale, $raw)->percent()->toDecimal())->toBe($percent);
})->with([
    'out of ten, whole' => [ScoringType::Score, '7', '70.0000'],
    'out of ten, two decimals' => [ScoringType::Score, '7.25', '72.5000'],
    'out of ten, maximum' => [ScoringType::Score, '10.00', '100.0000'],
    'relative, two decimals' => [ScoringType::Relative, '87.45', '87.4500'],
    'relative, zero' => [ScoringType::Relative, '0', '0.0000'],
    'achieved' => [ScoringType::Achievement, '1', '100.0000'],
    'not achieved' => [ScoringType::Achievement, '0.00', '0.0000'],
]);

it('scores unscored items as zero', function (): void {
    $tree = sampleScoringTree();
    $result = (new ScoringEngine)->compute($tree, ScoreSheet::empty());

    expect($result->total->toDecimal())->toBe('0.00000000')
        ->and($result->percent->toDisplay())->toBe('0.00')
        ->and($result->mains[0]->subs[0]->raw)->toBeNull();
});

it('rounds the display half up while storing full precision', function (): void {
    $tree = new ScoringTree(1, [
        new ScoringMain(1, 'M1', Weight::of('100.00'), [
            pointsSub(2, 'M1.1', '100.00', [[3, 'M1.1.1', '50.00'], [4, 'M1.1.2', '50.00']]),
        ]),
    ]);

    $result = (new ScoringEngine)->compute($tree, sheetFor($tree, ScoringType::Relative, ['M1.1.1' => '87.44', 'M1.1.2' => '87.45']));

    expect($result->mains[0]->subs[0]->percent->toDecimal())->toBe('87.4450')
        ->and($result->mains[0]->subs[0]->percent->toDisplay())->toBe('87.45')
        ->and($result->total->toDecimal())->toBe('87.44500000')
        ->and($result->total->toDisplay())->toBe('87.45');
});

it('adds weights in hundredths without drift', function (): void {
    $tree = new ScoringTree(1, [
        new ScoringMain(1, 'M1', Weight::of('100.00'), [
            directSub(2, 'M1.1', '33.34'),
            directSub(3, 'M1.2', '33.33'),
            directSub(4, 'M1.3', '33.33'),
        ]),
    ]);
    $engine = new ScoringEngine;

    $full = $engine->compute($tree, sheetFor($tree, ScoringType::Score, ['M1.1' => '10', 'M1.2' => '10', 'M1.3' => '10']));
    $mixed = $engine->compute($tree, sheetFor($tree, ScoringType::Score, ['M1.1' => '10', 'M1.2' => '5', 'M1.3' => '0']));

    expect($full->total->toDecimal())->toBe('100.00000000')
        ->and($full->percent->toDecimal())->toBe('100.0000')
        ->and($mixed->total->toDecimal())->toBe('50.00500000')
        ->and($mixed->total->toDisplay())->toBe('50.01')
        ->and($mixed->percent->toDecimal())->toBe('50.0050')
        ->and($mixed->percent->toDisplay())->toBe('50.01');
});

it('normalises guiding points that use less than the sub-criterion weight', function (): void {
    $tree = new ScoringTree(1, [
        new ScoringMain(1, 'M1', Weight::of('400.00'), [
            pointsSub(2, 'M1.1', '400.00', [[3, 'M1.1.1', '200.00'], [4, 'M1.1.2', '100.00']]),
        ]),
    ]);

    $result = (new ScoringEngine)->compute($tree, sheetFor($tree, ScoringType::Score, ['M1.1.1' => '10', 'M1.1.2' => '0']));

    expect($result->mains[0]->subs[0]->earned->toDecimal())->toBe('266.66666667')
        ->and($result->mains[0]->subs[0]->earned->toDisplay())->toBe('266.67')
        ->and($result->mains[0]->subs[0]->percent->toDecimal())->toBe('66.6667')
        ->and($result->percent->toDisplay())->toBe('66.67');
});

it('serialises the breakdown with full precision decimal strings', function (): void {
    $tree = sampleScoringTree();
    $breakdown = (new ScoringEngine)->compute($tree, sheetFor($tree, ScoringType::Score, ['M1.1' => '7.5']))->breakdown();

    expect($breakdown['mains'][0])->toMatchArray(['id' => 10, 'code' => 'M1', 'weight' => '1000.00', 'earned' => '450.00000000', 'percent' => '45.0000'])
        ->and($breakdown['mains'][0]['subs'][0])->toMatchArray(['code' => 'M1.1', 'evaluation_type' => 'direct', 'scoring_type' => 'score', 'raw_value' => '7.50', 'percent' => '75.0000'])
        ->and($breakdown['mains'][0]['subs'][1]['points'][0])->toMatchArray(['code' => 'M1.2.1', 'raw_value' => null, 'percent' => '0.0000', 'earned' => '0.00000000']);
});

it('lists the scorable units: direct sub-criteria and the guiding points of the others', function (): void {
    $codes = array_map(static fn (ScorableUnit $unit): string => $unit->code, sampleScoringTree()->scorableUnits());

    expect($codes)->toBe(['M1.1', 'M1.2.1', 'M1.2.2', 'M2.1']);
});

it('refuses raw values outside the scale or with more than two decimals', function (ScoringType $scale, string $raw): void {
    expect(fn (): RawScore => RawScore::on($scale, $raw))->toThrow(InvalidArgumentException::class);
})->with([
    'above ten' => [ScoringType::Score, '10.01'],
    'above one hundred' => [ScoringType::Relative, '100.5'],
    'three decimals' => [ScoringType::Relative, '50.125'],
    'negative' => [ScoringType::Score, '-1'],
    'half achieved' => [ScoringType::Achievement, '0.5'],
    'achievement above one' => [ScoringType::Achievement, '2'],
    'not a number' => [ScoringType::Score, 'seven'],
]);

it('scores a version that mixes the three types on one percent scale', function (): void {
    $tree = new ScoringTree(1, [new ScoringMain(10, 'M1', Weight::of('300.00'), [
        new ScoringSub(11, 'M1.1', Weight::of('300.00'), EvaluationType::GuidingPoints, ScoringType::Score, [
            new ScoringPoint(111, 'M1.1.1', Weight::of('100.00'), ScoringType::Achievement),
            new ScoringPoint(112, 'M1.1.2', Weight::of('100.00'), ScoringType::Relative),
            new ScoringPoint(113, 'M1.1.3', Weight::of('100.00'), ScoringType::Score),
        ]),
    ])]);
    $units = [];

    foreach ($tree->scorableUnits() as $unit) {
        $units[] = new ScoredUnit($unit, RawScore::on($unit->scoringType, ['M1.1.1' => '1', 'M1.1.2' => '40', 'M1.1.3' => '7'][$unit->code]));
    }

    $result = (new ScoringEngine)->compute($tree, ScoreSheet::of($units));

    expect($result->percent->toDisplay())->toBe('70.00')
        ->and($result->breakdown()['mains'][0]['subs'][0]['points'][0])->toMatchArray(['scoring_type' => 'achievement', 'raw_value' => '1.00', 'percent' => '100.0000']);
});
