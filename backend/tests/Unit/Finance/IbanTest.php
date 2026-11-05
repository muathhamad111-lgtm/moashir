<?php

declare(strict_types=1);

use App\Rules\SaudiIban;
use App\Support\ValueObjects\Iban;
use Illuminate\Support\Facades\Validator;

it('accepts the published registry example IBAN in any spacing and case', function (string $input): void {
    $iban = new Iban($input);

    expect($iban->value)->toBe(EXAMPLE_IBAN)->and($iban->lastFour())->toBe('7519');
})->with([
    'compact' => [EXAMPLE_IBAN],
    'groups of four' => ['SA03 8000 0000 6080 1016 7519'],
    'lower case with tabs' => ["sa03\t8000 0000 6080 1016 7519"],
]);

it('rejects one-character mutations of a valid IBAN', function (int $position): void {
    $mutated = EXAMPLE_IBAN;
    $mutated[$position] = $mutated[$position] === '9' ? '8' : (ctype_digit($mutated[$position]) ? (string) (((int) $mutated[$position] + 1) % 10) : 'B');

    expect(Iban::hasValidChecksum($mutated))->toBeFalse()
        ->and(fn (): Iban => new Iban($mutated))->toThrow(InvalidArgumentException::class);
})->with(range(2, 23));

it('rejects IBANs of the wrong length or of another country', function (string $input): void {
    expect(Iban::hasSaudiFormat(Iban::normalize($input)))->toBeFalse()
        ->and(fn (): Iban => new Iban($input))->toThrow(InvalidArgumentException::class);
})->with([
    'too short' => ['SA038000000060801016751'],
    'too long' => ['SA03800000006080101675190'],
    'british IBAN with a valid checksum' => ['GB82WEST12345698765432'],
    'letters in the bank code' => ['SA03AB00000608010167519'],
    'empty' => [''],
]);

it('validates the IBAN field with a format message and a checksum message', function (string $input, ?string $message): void {
    $validator = Validator::make(['iban' => $input], ['iban' => [new SaudiIban]]);

    expect($validator->errors()->first('iban'))->toBe($message === null ? '' : __($message));
})->with([
    'valid' => [EXAMPLE_IBAN, null],
    'wrong country' => ['GB82WEST12345698765432', 'validation.custom.iban.format'],
    'wrong check digits' => ['SA0480000000608010167519', 'validation.custom.iban.checksum'],
]);

it('builds fictional test IBANs that pass the checksum but use no assigned bank code', function (): void {
    foreach ([1, 2, 3, 77] as $serial) {
        $iban = fictionalIban($serial);

        expect((new Iban($iban))->value)->toBe($iban)->and(substr($iban, 4, 2))->toBe('00');
    }
});
