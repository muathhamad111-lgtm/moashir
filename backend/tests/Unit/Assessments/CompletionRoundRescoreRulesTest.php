<?php

declare(strict_types=1);

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoringMain;
use App\Data\Assessments\ScoringPoint;
use App\Data\Assessments\ScoringSub;
use App\Data\Assessments\ScoringTree;
use App\Enums\Assessments\CompletionRequestStatus;
use App\Enums\Criteria\EvaluationType;
use App\Enums\Criteria\ScoringType;
use App\Support\ValueObjects\Weight;

function rescoreRulesTree(): ScoringTree
{
    return new ScoringTree(1, [
        new ScoringMain(10, 'M1', Weight::of('1000.00'), [
            new ScoringSub(11, 'M1.1', Weight::of('600.00'), EvaluationType::Direct, ScoringType::Score),
            new ScoringSub(12, 'M1.2', Weight::of('400.00'), EvaluationType::GuidingPoints, ScoringType::Score, [
                new ScoringPoint(121, 'M1.2.1', Weight::of('200.00'), ScoringType::Score),
                new ScoringPoint(122, 'M1.2.2', Weight::of('200.00'), ScoringType::Score),
            ]),
        ]),
        new ScoringMain(20, 'M2', Weight::of('500.00'), [
            new ScoringSub(21, 'M2.1', Weight::of('500.00'), EvaluationType::Direct, ScoringType::Achievement),
        ]),
    ]);
}

/**
 * @param  list<string>  $subCodes
 * @return list<string>
 */
function rescoreRulesKeysUnder(array $subCodes): array
{
    return array_map(static fn (ScorableUnit $unit): string => $unit->key(), rescoreRulesTree()->scorableUnitsUnder($subCodes));
}

it('reads the guiding points of a sub-criterion scored through them and a direct sub-criterion itself, in tree order', function (): void {
    expect(rescoreRulesKeysUnder(['M2.1', 'M1.2']))->toBe(['point:121', 'point:122', 'sub:21'])
        ->and(rescoreRulesKeysUnder(['M1.1']))->toBe(['sub:11']);
});

it('reads no unit for a main criterion code, an unknown code or no code', function (): void {
    expect(rescoreRulesKeysUnder(['M1', 'M9.9', 'M1.2.1']))->toBe([])
        ->and(rescoreRulesKeysUnder([]))->toBe([]);
});

it('asks for a new result only after a resubmitted completion request', function (CompletionRequestStatus $status, bool $asks): void {
    expect($status->asksForRescore())->toBe($asks);
})->with([
    'resubmitted' => [CompletionRequestStatus::Resubmitted, true],
    'expired and continued' => [CompletionRequestStatus::ExpiredContinued, false],
    'expired and declined' => [CompletionRequestStatus::ExpiredDeclined, false],
    'cancelled' => [CompletionRequestStatus::Cancelled, false],
    'open' => [CompletionRequestStatus::Open, false],
]);
