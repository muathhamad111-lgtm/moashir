<?php

declare(strict_types=1);

use App\Data\Assessments\MissedCriteriaEdits;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Models\Criteria\CriteriaEditEvent;

/**
 * @param  list<array{code: string, score_affecting: bool}>  $changes
 */
function missedEdit(int $id, CriteriaEditScope $scope, int $from, array $changes = []): CriteriaEditEvent
{
    return (new CriteriaEditEvent)->forceFill([
        'id' => $id,
        'kind' => CriteriaEditKind::Structural,
        'scope' => $scope,
        'from_version_id' => $from,
        'to_version_id' => $from + 1,
        'changes' => $changes,
    ]);
}

it('orders the scopes from the strictest: reset_open, then all, then new_only', function (CriteriaEditScope $stricter, CriteriaEditScope $looser): void {
    expect($stricter->isStricterThan($looser))->toBeTrue()
        ->and($looser->isStricterThan($stricter))->toBeFalse()
        ->and($stricter->isStricterThan($stricter))->toBeFalse();
})->with([
    'reset_open over all' => [CriteriaEditScope::ResetOpen, CriteriaEditScope::All],
    'reset_open over new_only' => [CriteriaEditScope::ResetOpen, CriteriaEditScope::NewOnly],
    'all over new_only' => [CriteriaEditScope::All, CriteriaEditScope::NewOnly],
]);

it('applies the strictest scope of the missed publications', function (array $scopes, CriteriaEditScope $expected, bool $rebinds): void {
    $edits = array_map(static fn (CriteriaEditScope $scope, int $index): CriteriaEditEvent => missedEdit($index + 1, $scope, $index + 1), $scopes, array_keys($scopes));
    $missed = new MissedCriteriaEdits($edits);

    expect($missed->scope())->toBe($expected)
        ->and($missed->effect()->scope)->toBe($expected)
        ->and($missed->rebindsOpenAssessments())->toBe($rebinds);
})->with([
    'one all' => [[CriteriaEditScope::All], CriteriaEditScope::All, true],
    'new_only then all' => [[CriteriaEditScope::NewOnly, CriteriaEditScope::All], CriteriaEditScope::All, true],
    'reset_open first' => [[CriteriaEditScope::ResetOpen, CriteriaEditScope::NewOnly, CriteriaEditScope::All], CriteriaEditScope::ResetOpen, true],
    'reset_open last' => [[CriteriaEditScope::All, CriteriaEditScope::ResetOpen], CriteriaEditScope::ResetOpen, true],
    'only new_only' => [[CriteriaEditScope::NewOnly, CriteriaEditScope::NewOnly], CriteriaEditScope::NewOnly, false],
]);

it('targets the version published last and lists every missed publication', function (): void {
    $missed = new MissedCriteriaEdits([missedEdit(4, CriteriaEditScope::NewOnly, 7), missedEdit(12, CriteriaEditScope::All, 8)]);

    expect($missed->targetVersionId())->toBe(9)
        ->and($missed->editEventIds())->toBe([4, 12]);
});

it('re-assesses the union of the score-affecting changes of every missed publication', function (): void {
    $missed = new MissedCriteriaEdits([
        missedEdit(1, CriteriaEditScope::NewOnly, 1, [
            ['code' => 'M1.1', 'score_affecting' => true],
            ['code' => 'M1.2', 'score_affecting' => false],
        ]),
        missedEdit(2, CriteriaEditScope::All, 2, [
            ['code' => 'M2.1', 'score_affecting' => true],
            ['code' => 'M1.1', 'score_affecting' => true],
        ]),
    ]);

    expect($missed->rescoredCodes())->toBe(['M1.1', 'M2.1']);
});

it('misses nothing when no publication replaced the version', function (): void {
    $missed = new MissedCriteriaEdits([]);

    expect($missed->targetVersionId())->toBeNull()
        ->and($missed->scope())->toBe(CriteriaEditScope::NewOnly)
        ->and($missed->rebindsOpenAssessments())->toBeFalse()
        ->and($missed->rescoredCodes())->toBe([])
        ->and($missed->editEventIds())->toBe([]);
});
