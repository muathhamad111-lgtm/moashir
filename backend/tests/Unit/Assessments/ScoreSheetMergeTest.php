<?php

declare(strict_types=1);

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScoringType;
use App\Support\ValueObjects\RawScore;

it('merges partial score changes into the current sheet and clears the listed items', function (): void {
    $policy = new ScorableUnit(ScoreLevel::Sub, 1, 'M1.1', ScoringType::Score);
    $point = new ScorableUnit(ScoreLevel::Point, 7, 'M1.2.1', ScoringType::Score);
    $results = new ScorableUnit(ScoreLevel::Sub, 3, 'M2.1', ScoringType::Score);
    $current = ScoreSheet::of([
        new ScoredUnit($policy, RawScore::on(ScoringType::Score, '6')),
        new ScoredUnit($point, RawScore::on(ScoringType::Score, '9')),
    ]);

    $merged = $current->merge(ScoreSheet::of([new ScoredUnit($results, RawScore::on(ScoringType::Score, '7.5'))]), [$point->key()]);

    expect($merged->count())->toBe(2)
        ->and($merged->rawFor($policy)?->toDecimal())->toBe('6.00')
        ->and($merged->rawFor($results)?->toDecimal())->toBe('7.50')
        ->and($merged->has($point))->toBeFalse();
});
