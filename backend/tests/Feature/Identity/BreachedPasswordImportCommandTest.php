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

it('imports every bundled digest list of a directory, the SecLists list included', function (): void {
    $this->artisan('moashir:breached-passwords:import', ['path' => database_path('data/breached-passwords')])
        ->expectsOutputToContain('curated-seed.sha256')
        ->expectsOutputToContain('seclists-xato-top-1m-min12.sha256')
        ->assertSuccessful();

    expect(BreachedPassword::query()->count())->toBeGreaterThanOrEqual(46296)
        ->and(app(BreachedPasswordChecker::class)->isBreached('123456789012'))->toBeTrue()
        ->and(app(BreachedPasswordChecker::class)->isBreached('Wadi-Hanifa-Spring-2026'))->toBeFalse();
});

it('keeps the bundled SecLists list as digests of 12 to 128 characters only, with its licence', function (): void {
    $lines = file(database_path('data/breached-passwords/seclists-xato-top-1m-min12.sha256'), FILE_IGNORE_NEW_LINES) ?: [];

    expect($lines)->toHaveCount(46296)
        ->and(array_filter($lines, static fn (string $line): bool => preg_match('/^[0-9a-f]{64}$/', $line) !== 1))->toBe([])
        ->and(in_array(hash('sha256', '123456789012'), $lines, true))->toBeTrue()
        ->and(in_array(hash('sha256', '123456'), $lines, true))->toBeFalse()
        ->and((string) file_get_contents(database_path('data/breached-passwords/SECLISTS-LICENSE.txt')))->toContain('MIT License');
});
