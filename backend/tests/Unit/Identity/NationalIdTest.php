<?php

declare(strict_types=1);

use App\Enums\Identity\NationalIdProblem;
use App\Support\ValueObjects\NationalId;

it('accepts any 10 digits starting with 1 or 2, without a check digit', function (string $number): void {
    expect((new NationalId($number))->value)->toBe($number);
})->with([
    'citizen' => '1000000008',
    'resident' => '2000000001',
    'failed the old check digit' => '1000000009',
]);

it('names the first rule a number breaks', function (string $input, ?NationalIdProblem $problem): void {
    expect(NationalId::problemOf(NationalId::normalize($input)))->toBe($problem);

    if ($problem instanceof NationalIdProblem) {
        expect(NationalId::tryFrom($input))->toBeNull()
            ->and(fn (): NationalId => new NationalId($input))->toThrow(InvalidArgumentException::class);
    }
})->with([
    'valid' => ['1000000008', null],
    'letters' => ['10000a0008', NationalIdProblem::NotDigits],
    'symbols' => ['1000-00008', NationalIdProblem::NotDigits],
    'empty' => ['', NationalIdProblem::NotDigits],
    'nine digits' => ['100000000', NationalIdProblem::WrongLength],
    'eleven digits' => ['10000000080', NationalIdProblem::WrongLength],
    'starts with 3' => ['3000000001', NationalIdProblem::WrongPrefix],
    'starts with 0' => ['0000000000', NationalIdProblem::WrongPrefix],
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
