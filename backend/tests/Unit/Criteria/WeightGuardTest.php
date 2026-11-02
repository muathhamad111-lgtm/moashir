<?php

declare(strict_types=1);

use App\Data\Criteria\WeightBudget;
use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Services\Criteria\WeightGuard;
use App\Support\ValueObjects\Weight;

it('computes the remaining weight and never reports it below zero', function (): void {
    $guard = new WeightGuard;

    expect($guard->remaining(Weight::of('1000'), Weight::of('600'))->toDecimal())->toBe('400.00')
        ->and($guard->remaining(Weight::of('1000'), Weight::of('1200'))->toDecimal())->toBe('0.00');
});

it('accepts a weight up to the remaining weight and blocks anything above it', function (): void {
    $guard = new WeightGuard;
    $guard->assertFits(Weight::of('1000'), Weight::of('600'), Weight::of('400'));

    try {
        $guard->assertFits(Weight::of('1000'), Weight::of('600'), Weight::of('400.01'));
        $this->fail('The guard accepted a weight above the remaining weight.');
    } catch (CriteriaWeightRejected $rejected) {
        expect($rejected->error()->code)->toBe('criteria.weight_exceeds_remaining')
            ->and($rejected->error()->meta)->toBe(['remaining_weight' => '400.00'])
            ->and($rejected->error()->status)->toBe(422);
    }
});

it('blocks a parent weight below the weight already allocated to its children', function (): void {
    $guard = new WeightGuard;
    $guard->assertCovers(Weight::of('600'), Weight::of('600'));

    expect(fn () => $guard->assertCovers(Weight::of('599.99'), Weight::of('600')))->toThrow(CriteriaWeightRejected::class);
});

it('resolves a child weight from the parent budget', function (): void {
    $guard = new WeightGuard;
    $manual = new WeightBudget(Weight::of('500'), Weight::of('300'), false);
    $distributed = new WeightBudget(Weight::of('500'), Weight::of('300'), true);

    expect($guard->childWeight($manual, Weight::of('200'), true)->toDecimal())->toBe('200.00')
        ->and($guard->childWeight($manual, Weight::of('900'), false)->toDecimal())->toBe('900.00')
        ->and($guard->childWeight($distributed, null, true)->isPositive())->toBeTrue()
        ->and(fn (): Weight => $guard->childWeight($manual, null, true))->toThrow(CriteriaWeightRejected::class)
        ->and(fn (): Weight => $guard->childWeight($manual, Weight::of('200.01'), true))->toThrow(CriteriaWeightRejected::class);
});
