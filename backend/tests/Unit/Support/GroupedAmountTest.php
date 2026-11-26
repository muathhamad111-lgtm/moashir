<?php

declare(strict_types=1);

use App\Support\GroupedAmount;

it('groups the thousands of a stored amount and keeps its fraction as stored', function (string $amount, string $printed): void {
    expect(GroupedAmount::format($amount))->toBe($printed);
})->with([
    'four digits' => ['1500.00', '1,500.00'],
    'three digits' => ['225.00', '225.00'],
    'seven digits, one fraction digit' => ['1234567.5', '1,234,567.5'],
    'whole thousand without fraction' => ['1000', '1,000'],
    'zero' => ['0.00', '0.00'],
]);
