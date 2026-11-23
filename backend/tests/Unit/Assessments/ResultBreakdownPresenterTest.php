<?php

declare(strict_types=1);

use App\Data\Assessments\CriteriaNodeNames;
use App\Support\Assessments\ResultBreakdownPresenter;

/**
 * @param  array<string, string>  $subTypes  the scoring_type stored per sub (none for a snapshot taken before the types)
 * @return array<string, mixed>
 */
function storedBreakdown(array $subTypes = [], ?string $pointType = null): array
{
    $point = ['id' => 7, 'code' => 'M1.2.1', 'weight' => '200.00', 'earned' => '180.00', 'percent' => '90.00', 'raw_value' => '9.00'];
    $sub = static fn (int $id, string $code, string $raw): array => array_filter([
        'id' => $id, 'code' => $code, 'evaluation_type' => 'direct', 'weight' => '600.00', 'earned' => '600.00', 'percent' => '100.00', 'raw_value' => $raw,
        'scoring_type' => $subTypes[$code] ?? null,
    ], static fn (mixed $value): bool => $value !== null);

    return ['mains' => [[
        'id' => 1, 'code' => 'M1', 'weight' => '1000.00', 'earned' => '780.00', 'percent' => '78.00',
        'subs' => [
            $sub(1, 'M1.1', '1.00'),
            ['id' => 2, 'code' => 'M1.2', 'evaluation_type' => 'guiding_points', 'weight' => '400.00', 'earned' => '180.00', 'percent' => '45.00', 'raw_value' => null,
                'points' => [$pointType === null ? $point : [...$point, 'scoring_type' => $pointType]]],
        ],
    ]]];
}

it('passes each unit scoring type stored in the snapshot to the result rows', function (): void {
    $breakdown = new ResultBreakdownPresenter(storedBreakdown(['M1.1' => 'achievement'], 'score'), new CriteriaNodeNames([], [], []));

    expect($breakdown->subs()[0])->toMatchArray(['code' => 'M1.1', 'scoring_type' => 'achievement', 'raw_value' => '1.00'])
        ->and($breakdown->points()[0])->toMatchArray(['code' => 'M1.2.1', 'scoring_type' => 'score', 'raw_value' => '9.00']);
});

it('gives no scoring type for a snapshot taken before the scoring types', function (): void {
    $breakdown = new ResultBreakdownPresenter(storedBreakdown(), new CriteriaNodeNames([], [], []));

    expect($breakdown->subs()[0])->toHaveKey('scoring_type', null)
        ->and($breakdown->subs()[0]['raw_value'])->toBe('1.00')
        ->and($breakdown->points()[0])->toHaveKey('scoring_type', null);
});
