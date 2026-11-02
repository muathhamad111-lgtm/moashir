<?php

declare(strict_types=1);

use App\Data\Criteria\ConsistencyProblem;
use App\Enums\Criteria\EvaluationType;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Services\Criteria\CriteriaConsistencyChecker;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Collection;

function memoryPoint(int $id, string $weight, bool $active = true): GuidingPoint
{
    return (new GuidingPoint)->forceFill(['id' => $id, 'code' => 'P'.$id, 'weight' => Weight::of($weight), 'is_active' => $active]);
}

/**
 * @param  list<GuidingPoint>  $points
 */
function memorySub(int $id, string $weight, EvaluationType $type, array $points = [], bool $active = true): SubCriterion
{
    $sub = (new SubCriterion)->forceFill(['id' => $id, 'code' => 'S'.$id, 'weight' => Weight::of($weight), 'evaluation_type' => $type, 'is_active' => $active]);

    return $sub->setRelation('guidingPoints', new Collection($points));
}

/**
 * @param  list<SubCriterion>  $subs
 */
function memoryMain(int $id, string $weight, array $subs, bool $active = true): MainCriterion
{
    $main = (new MainCriterion)->forceFill(['id' => $id, 'code' => 'M'.$id, 'weight' => Weight::of($weight), 'is_active' => $active]);

    return $main->setRelation('subCriteria', new Collection($subs));
}

/**
 * @param  list<MainCriterion>  $mains
 */
function memoryVersion(array $mains): CriteriaVersion
{
    return (new CriteriaVersion)->setRelation('mainCriteria', new Collection($mains));
}

/**
 * @return list<string>
 */
function problemCodes(CriteriaVersion $version): array
{
    return array_map(static fn (ConsistencyProblem $problem): string => $problem->problem->value.':'.$problem->code, (new CriteriaConsistencyChecker)->check($version)->problems);
}

it('accepts a tree whose sub-criteria match the main weight and whose points stay within the sub weight', function (): void {
    $version = memoryVersion([memoryMain(1, '1000', [
        memorySub(11, '600', EvaluationType::GuidingPoints, [memoryPoint(111, '300'), memoryPoint(112, '250')]),
        memorySub(12, '400', EvaluationType::Direct),
    ])]);

    expect((new CriteriaConsistencyChecker)->check($version)->isConsistent())->toBeTrue();
});

it('requires at least one active main criterion', function (): void {
    expect(problemCodes(memoryVersion([])))->toBe(['no_active_main:'])
        ->and(problemCodes(memoryVersion([memoryMain(1, '100', [], false)])))->toBe(['no_active_main:']);
});

it('reports every inconsistent node and ignores inactive items', function (): void {
    $version = memoryVersion([
        memoryMain(1, '1000', [
            memorySub(11, '600', EvaluationType::GuidingPoints, [memoryPoint(111, '400'), memoryPoint(112, '300')]),
            memorySub(12, '300', EvaluationType::Direct),
            memorySub(13, '500', EvaluationType::Direct, [], false),
        ]),
        memoryMain(2, '200', [
            memorySub(21, '200', EvaluationType::GuidingPoints, [memoryPoint(211, '100', false)]),
        ]),
        memoryMain(3, '50', [], false),
    ]);

    expect(problemCodes($version))->toBe(['weights_mismatch:M1', 'points_exceed_sub:S11', 'no_active_point:S21']);
});

it('ignores guiding points under a directly evaluated sub-criterion', function (): void {
    $version = memoryVersion([memoryMain(1, '100', [memorySub(11, '100', EvaluationType::Direct, [memoryPoint(111, '900')])])]);

    expect((new CriteriaConsistencyChecker)->check($version)->isConsistent())->toBeTrue();
});
