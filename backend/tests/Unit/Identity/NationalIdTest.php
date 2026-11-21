<?php

declare(strict_types=1);

use App\Support\ValueObjects\NationalId;

it('accepts citizen and resident numbers with a valid check digit', function (string $number): void {
    expect((new NationalId($number))->value)->toBe($number);
})->with([
    'citizen' => fn (): string => fictionalNationalId(1234, 1),
    'resident' => fn (): string => fictionalNationalId(5678, 2),
]);

it('rejects numbers with the wrong shape or check digit', function (string $input): void {
    expect(NationalId::tryFrom($input))->toBeNull()
        ->and(fn (): NationalId => new NationalId($input))->toThrow(InvalidArgumentException::class);
})->with([
    'nine digits' => '100000000',
    'eleven digits' => '10000000080',
    'letters' => '10000a0008',
    'starts with 3' => '3000000001',
    'starts with 0' => '0000000000',
    'bad check digit' => '1000000009',
    'empty' => '',
]);

it('normalizes Eastern Arabic digits and spaces', function (): void {
    $number = fictionalNationalId(42);
    $eastern = strtr($number, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    expect((new NationalId(' '.$eastern.' '))->value)->toBe($number);
});

it('masks all digits but the last four', function (): void {
    $number = fictionalNationalId(77);

    expect(new NationalId($number)->masked())->toBe('••••••'.substr($number, -4));
});
