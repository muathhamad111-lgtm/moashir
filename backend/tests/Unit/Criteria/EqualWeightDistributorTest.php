<?php

declare(strict_types=1);

use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Services\Criteria\EqualWeightDistributor;
use App\Support\ValueObjects\Weight;

it('splits a weight into equal shares and gives the remainder to the first items', function (string $total, int $count, array $expected): void {
    $shares = (new EqualWeightDistributor)->shares(Weight::of($total), $count);

    expect(array_map(static fn (Weight $share): string => $share->toDecimal(), $shares))->toBe($expected)
        ->and(Weight::sum($shares)->equals(Weight::of($total)))->toBeTrue();
})->with([
    'three shares of 400' => ['400', 3, ['133.34', '133.33', '133.33']],
    'two shares of 1000' => ['1000', 2, ['500.00', '500.00']],
    'seven shares of 100' => ['100', 7, ['14.29', '14.29', '14.29', '14.29', '14.28', '14.28', '14.28']],
    'one share' => ['250.50', 1, ['250.50']],
]);

it('returns no shares when there is nothing to distribute to', function (): void {
    expect((new EqualWeightDistributor)->shares(Weight::of('100'), 0))->toBe([]);
});

it('refuses a distribution that would create a zero share', function (): void {
    expect(fn (): array => (new EqualWeightDistributor)->shares(Weight::of('0.02'), 3))->toThrow(CriteriaWeightRejected::class);
});
