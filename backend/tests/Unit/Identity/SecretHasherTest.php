<?php

declare(strict_types=1);

use App\Services\Identity\SecretHasher;

it('verifies a secret against its keyed hash', function (): void {
    $hasher = app(SecretHasher::class);
    $hash = $hasher->hash('482913');

    expect($hash)->not->toContain('482913')
        ->and($hasher->matches('482913', $hash))->toBeTrue()
        ->and($hasher->matches('482914', $hash))->toBeFalse();
});

it('depends on the application key', function (): void {
    $hash = app(SecretHasher::class)->hash('482913');
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect(app(SecretHasher::class)->matches('482913', $hash))->toBeFalse();
});
