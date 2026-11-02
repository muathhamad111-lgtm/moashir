<?php

declare(strict_types=1);

use App\Enums\Criteria\ScaleKind;
use App\Support\FixedPoint;
use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\Weight;

it('rounds half up when dividing fixed-point values', function (int $numerator, int $denominator, int $expected): void {
    expect(FixedPoint::divideRoundingHalfUp($numerator, $denominator))->toBe($expected);
})->with([
    'exact' => [10, 5, 2],
    'below half' => [14, 10, 1],
    'half' => [15, 10, 2],
    'above half' => [16, 10, 2],
    'two thirds' => [2, 3, 1],
]);

it('scales large weighted sums without overflowing', function (): void {
    $weightedPercents = 1_000_000 * 10_000_000 * 50;

    expect(FixedPoint::multiplyDivideRoundingHalfUp($weightedPercents, 10_000_000, 10_000_000 * 50))->toBe(1_000_000 * 10_000_000);
});

it('parses and formats decimals without floats', function (): void {
    expect(FixedPoint::parse('87.4500', 4))->toBe(874_500)
        ->and(FixedPoint::parse('7.5', 2))->toBe(750)
        ->and(FixedPoint::parse('7.125', 2))->toBeNull()
        ->and(FixedPoint::parse('-1', 2))->toBeNull()
        ->and(FixedPoint::format(874_500, 4))->toBe('87.4500')
        ->and(FixedPoint::format(5, 2))->toBe('0.05');
});

it('shows percentages and earned scores with two decimals rounded half up', function (): void {
    expect(Percent::of('87.4450')->toDisplay())->toBe('87.45')
        ->and(Percent::of('87.4449')->toDisplay())->toBe('87.44')
        ->and(EarnedScore::of('266.66666667')->toDisplay())->toBe('266.67')
        ->and(EarnedScore::of('50.00500000')->toDisplay())->toBe('50.01');
});

it('derives the earned score and its percentage of a weight', function (): void {
    $earned = EarnedScore::ofPercentOfWeight(Percent::of('33.3333'), Weight::of('7.77'));

    expect($earned->toDecimal())->toBe('2.58999741')
        ->and($earned->percentOf(Weight::of('7.77'))->toDecimal())->toBe('33.3333')
        ->and(EarnedScore::zero()->percentOf(Weight::zero())->toDecimal())->toBe('0.0000');
});

it('refuses percentages outside 0 to 100', function (string $value): void {
    expect(fn (): Percent => Percent::of($value))->toThrow(InvalidArgumentException::class);
})->with(['100.0001', '-5', 'abc']);

it('knows the maximum of each scale', function (): void {
    expect(ScaleKind::OutOfTen->maximum())->toBe('10.00')
        ->and(ScaleKind::Relative->maximum())->toBe('100.00');
});
