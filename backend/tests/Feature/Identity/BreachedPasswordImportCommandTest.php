<?php

declare(strict_types=1);

use App\Contracts\BreachedPasswordChecker;
use App\Models\Identity\BreachedPassword;
use Illuminate\Support\Facades\Http;

it('imports a plain list from the console without any network call', function (): void {
    Http::fake(static fn (): never => throw new RuntimeException('Outbound HTTP is forbidden.'));
    $path = temporaryListFile("Asir-Mountains-2026\nFarasan-Island-2026\n\n");

    $this->artisan('moashir:breached-passwords:import', ['path' => $path])
        ->expectsOutputToContain('2 entries read: 2 added, 0 already listed, 0 rejected as malformed.')
        ->assertSuccessful();

    expect(app(BreachedPasswordChecker::class)->isBreached('Farasan-Island-2026'))->toBeTrue();
    Http::assertNothingSent();
});

it('imports a digest list from the console', function (): void {
    $path = temporaryListFile(hash('sha256', 'Jazan-Coast-2026')."\nmalformed\n");

    $this->artisan('moashir:breached-passwords:import', ['path' => $path, '--format' => 'sha256'])
        ->expectsOutputToContain('2 entries read: 1 added, 0 already listed, 1 rejected as malformed.')
        ->assertSuccessful();

    expect(BreachedPassword::query()->count())->toBe(1);
});

it('rejects an unknown format', function (): void {
    $this->artisan('moashir:breached-passwords:import', ['path' => temporaryListFile("x\n"), '--format' => 'sha1'])
        ->expectsOutputToContain('Unknown format')
        ->assertExitCode(2);

    expect(BreachedPassword::query()->count())->toBe(0);
});

it('fails on a missing file without revealing its directory', function (): void {
    $this->artisan('moashir:breached-passwords:import', ['path' => '/srv/private/lists/missing.txt'])
        ->expectsOutputToContain('[missing.txt]')
        ->doesntExpectOutputToContain('/srv/private')
        ->assertFailed();
});
