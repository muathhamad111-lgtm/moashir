<?php

declare(strict_types=1);

use App\Data\Criteria\CriteriaChange;
use App\Enums\Criteria\CriteriaChangeType;
use App\Enums\Criteria\CriteriaNodeType;
use App\Enums\Criteria\CriterionGroup;
use App\Enums\Criteria\EvaluationType;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Services\Criteria\CriteriaNodeSnapshot;
use App\Services\Criteria\CriteriaVersionDiff;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Collection;

/**
 * @param  array<string, array{string, EvaluationType}>  $subs
 */
function diffVersion(string $mainWeight, array $subs): CriteriaVersion
{
    $subModels = [];
    $id = 10;

    foreach ($subs as $code => [$weight, $type]) {
        $subModels[] = (new SubCriterion)->forceFill([
            'id' => ++$id, 'code' => $code, 'name' => ['ar' => 'معيار فرعي '.$code, 'en' => 'Sub-criterion '.$code],
            'weight' => Weight::of($weight), 'distribute_equally' => false, 'evaluation_type' => $type, 'has_cv' => true,
            'is_active' => true, 'sort_order' => $id,
        ])->setRelation('guidingPoints', new Collection)->setRelation('attachmentRequirements', new Collection);
    }

    $main = (new MainCriterion)->forceFill([
        'id' => 1, 'code' => 'M1', 'name' => ['ar' => 'القيادة الإدارية', 'en' => 'Administrative Leadership'],
        'weight' => Weight::of($mainWeight), 'distribute_equally' => false, 'group' => CriterionGroup::Enablers,
        'is_active' => true, 'sort_order' => 1,
    ])->setRelation('subCriteria', new Collection($subModels));

    return (new CriteriaVersion)->setRelation('mainCriteria', new Collection([$main]));
}

/**
 * @return array<string, string>
 */
function changeSummary(CriteriaVersion $from, CriteriaVersion $to): array
{
    $summary = [];

    foreach (new CriteriaVersionDiff(new CriteriaNodeSnapshot)->between($from, $to) as $change) {
        $summary[$change->code] = $change->change->value.($change->isScoreAffecting() ? '*' : '').':'.implode(',', $change->fields);
    }

    return $summary;
}

it('finds no change between identical trees', function (): void {
    $tree = ['M1.1' => ['600', EvaluationType::GuidingPoints], 'M1.2' => ['400', EvaluationType::Direct]];

    expect(changeSummary(diffVersion('1000', $tree), diffVersion('1000', $tree)))->toBe([]);
});

it('matches items by code and classifies added, removed and modified items', function (): void {
    $from = diffVersion('1000', ['M1.1' => ['600', EvaluationType::GuidingPoints], 'M1.2' => ['400', EvaluationType::Direct]]);
    $to = diffVersion('1000', ['M1.1' => ['500', EvaluationType::Direct], 'M1.3' => ['500', EvaluationType::Direct]]);

    expect(changeSummary($from, $to))->toBe([
        'M1.1' => 'modified*:weight,evaluation_type',
        'M1.3' => 'added*:',
        'M1.2' => 'removed*:',
    ]);
});

it('treats text-only differences as changes that keep scores', function (): void {
    $change = new CriteriaChange(CriteriaNodeType::Sub, 'M1.1', CriteriaChangeType::Modified, 11, 21, ['name', 'order']);
    $corrected = new CriteriaChange(CriteriaNodeType::Main, 'M1', CriteriaChangeType::TextCorrected, 1, 1, ['name']);

    expect($change->isScoreAffecting())->toBeFalse()
        ->and($corrected->isScoreAffecting())->toBeFalse()
        ->and($change->toArray())->toMatchArray(['node_type' => 'sub', 'code' => 'M1.1', 'change' => 'modified', 'score_affecting' => false]);
});
