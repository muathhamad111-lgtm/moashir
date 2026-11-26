<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentStatus;
use App\Rules\EnumValueList;
use Illuminate\Support\Facades\Validator;

it('parses a comma list of enum values without duplicates or unknown values', function (): void {
    expect(EnumValueList::parse(' created, pending ,created', PaymentStatus::class))->toBe([PaymentStatus::Created, PaymentStatus::Pending])
        ->and(EnumValueList::parse('paid', PaymentStatus::class))->toBe([PaymentStatus::Paid])
        ->and(EnumValueList::parse('', PaymentStatus::class))->toBe([])
        ->and(EnumValueList::parse(null, PaymentStatus::class))->toBe([]);
});

it('accepts only lists made of known values', function (mixed $value, bool $passes): void {
    $validator = Validator::make(['status' => $value], ['status' => [new EnumValueList(PaymentStatus::class)]]);

    expect($validator->passes())->toBe($passes);
})->with([
    'one value' => ['paid', true],
    'several values' => ['cancelled,failed', true],
    'spaces around values' => ['created , pending', true],
    'unknown value' => ['settled', false],
    'one unknown value in a list' => ['paid,settled', false],
    'empty item' => ['paid,,failed', false],
    'trailing separator' => ['paid,', false],
    'not a string' => [['paid'], false],
]);
