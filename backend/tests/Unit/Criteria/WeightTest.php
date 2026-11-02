<?php

declare(strict_types=1);

use App\Support\ValueObjects\Weight;

it('parses decimal input without floating point drift', function (string|int|float $input, int $hundredths, string $decimal): void {
    $weight = Weight::of($input);

    expect($weight->hundredths)->toBe($hundredths)->and($weight->toDecimal())->toBe($decimal);
})->with([
    'integer' => [1000, 100000, '1000.00'],
    'string with two decimals' => ['133.34', 13334, '133.34'],
    'string with one decimal' => ['0.5', 50, '0.50'],
    'float' => [133.33, 13333, '133.33'],
    'database value' => ['400.00', 40000, '400.00'],
    'negative difference' => ['-5.25', -525, '-5.25'],
]);

it('refuses malformed weights', function (string $input): void {
    expect(fn (): Weight => Weight::of($input))->toThrow(InvalidArgumentException::class);
})->with(['1.005', 'abc', '', '1e3', '12,5']);

it('adds, subtracts and compares weights', function (): void {
    $total = Weight::sum([Weight::of('133.34'), Weight::of('133.33'), Weight::of('133.33')]);

    expect($total->equals(Weight::of('400')))->toBeTrue()
        ->and(Weight::of('400')->minus(Weight::of('450'))->toDecimal())->toBe('-50.00')
        ->and(Weight::of('10')->plus(Weight::of('0.01'))->toDecimal())->toBe('10.01')
        ->and(Weight::of('10')->isGreaterThan(Weight::of('9.99')))->toBeTrue()
        ->and(Weight::of('9.99')->isLessThan(Weight::of('10')))->toBeTrue()
        ->and(Weight::zero()->isPositive())->toBeFalse();
});
