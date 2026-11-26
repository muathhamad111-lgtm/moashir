<?php

declare(strict_types=1);

use App\Services\Certificates\VerificationCode;

it('generates unguessable codes of at least 128 bits from the Crockford alphabet', function (): void {
    $codes = app(VerificationCode::class);
    $generated = array_map($codes->generate(...), range(1, 50));

    foreach ($generated as $code) {
        expect($code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{28}$/');
    }

    expect(array_unique($generated))->toHaveCount(50)
        ->and(28 * log(32, 2))->toBeGreaterThanOrEqual(128.0);
});

it('normalizes typed codes and groups them for display', function (): void {
    $codes = app(VerificationCode::class);
    $code = $codes->generate();
    $display = VerificationCode::display($code);

    expect($display)->toMatch('/^([0-9A-Z]{4}-){6}[0-9A-Z]{4}$/')
        ->and($codes->normalize(strtolower($display)))->toBe($code)
        ->and($codes->normalize(' '.str_replace('-', ' ', $display).' '))->toBe($code)
        ->and($codes->normalize('SHORT'))->toBeNull()
        ->and($codes->normalize(str_repeat('U', 28)))->toBeNull()
        ->and($codes->normalize('<script>'))->toBeNull();
});
