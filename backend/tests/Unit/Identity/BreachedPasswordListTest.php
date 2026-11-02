<?php

declare(strict_types=1);

use App\Actions\Identity\ImportBreachedPasswords;
use App\Contracts\BreachedPasswordChecker;
use App\Data\Identity\BreachedListImport;
use App\Data\Identity\BreachedListImportResult;
use App\Enums\Identity\BreachedListFormat;
use App\Exceptions\Console\BreachedListUnreadable;
use App\Models\Identity\BreachedPassword;
use App\Services\Identity\LocalBreachedPasswordChecker;
use App\Support\ValueObjects\PasswordDigest;
use Database\Seeders\BreachedPasswordSeeder;
use Illuminate\Support\Facades\Http;

function importBreachedList(string $contents, BreachedListFormat $format = BreachedListFormat::Plain): BreachedListImportResult
{
    return app(ImportBreachedPasswords::class)->handle(new BreachedListImport(temporaryListFile($contents), $format));
}

it('binds the local checker to the contract', function (): void {
    expect(app(BreachedPasswordChecker::class))->toBeInstanceOf(LocalBreachedPasswordChecker::class);
});

it('flags exactly the passwords on the local list without any network call', function (): void {
    Http::fake(static fn (): never => throw new RuntimeException('Outbound HTTP is forbidden.'));
    markPasswordBreached('Diriyah-Gate-2026');
    $checker = app(BreachedPasswordChecker::class);

    expect($checker->isBreached('Diriyah-Gate-2026'))->toBeTrue()
        ->and($checker->isBreached('diriyah-gate-2026'))->toBeFalse()
        ->and($checker->isBreached('Al-Ula-Oasis-Morning-2026'))->toBeFalse();
    Http::assertNothingSent();
});

it('stores SHA-256 digests only, never the password itself', function (): void {
    importBreachedList("Qassim-Palms-2026\n");

    expect(BreachedPassword::query()->pluck('digest')->all())->toBe([hash('sha256', 'Qassim-Palms-2026')]);
});

it('imports a plain list idempotently and skips blank lines', function (): void {
    $first = importBreachedList("Tabuk-Roses-2026\r\n\nAbha-Clouds-2026\nTabuk-Roses-2026\n");
    $second = importBreachedList("Tabuk-Roses-2026\nNajran-Valley-2026\n");

    expect([$first->read, $first->added, $first->rejected, $first->alreadyListed()])->toBe([3, 2, 0, 1])
        ->and([$second->read, $second->added, $second->alreadyListed()])->toBe([2, 1, 1])
        ->and(BreachedPassword::query()->count())->toBe(3);
});

it('imports a digest list and rejects malformed lines', function (): void {
    $digest = hash('sha256', 'Hail-Desert-2026');

    $result = importBreachedList(mb_strtoupper($digest)."\nnot-a-digest\n".str_repeat('a', 63)."\n", BreachedListFormat::Sha256);

    expect([$result->read, $result->added, $result->rejected])->toBe([3, 1, 2])
        ->and(app(BreachedPasswordChecker::class)->isBreached('Hail-Desert-2026'))->toBeTrue();
});

it('refuses a list file that does not exist', function (): void {
    expect(fn (): mixed => app(ImportBreachedPasswords::class)->handle(new BreachedListImport('/nonexistent/breached.txt', BreachedListFormat::Plain)))
        ->toThrow(BreachedListUnreadable::class);
});

it('validates password digests', function (): void {
    expect(PasswordDigest::fromHex(' '.mb_strtoupper(hash('sha256', 'x')).' ')->value)->toBe(hash('sha256', 'x'))
        ->and(PasswordDigest::tryFromHex('xyz'))->toBeNull()
        ->and(fn (): PasswordDigest => PasswordDigest::fromHex(str_repeat('g', 64)))->toThrow(InvalidArgumentException::class);
});

it('ships a well-formed curated seed list', function (): void {
    $lines = file(database_path(BreachedPasswordSeeder::CURATED_LIST), FILE_IGNORE_NEW_LINES);
    $sorted = $lines;
    sort($sorted);

    expect($lines)->not->toBeEmpty()
        ->and($lines)->toBe($sorted)
        ->and(array_unique($lines))->toBe($lines)
        ->and(array_filter($lines, static fn (string $line): bool => PasswordDigest::tryFromHex($line)?->value !== $line))->toBe([]);
});

it('seeds the curated list once and rejects well-known weak passwords', function (): void {
    $this->seed(BreachedPasswordSeeder::class);
    $this->seed(BreachedPasswordSeeder::class);
    $checker = app(BreachedPasswordChecker::class);

    expect(BreachedPassword::query()->count())->toBe(count(file(database_path(BreachedPasswordSeeder::CURATED_LIST), FILE_IGNORE_NEW_LINES)))
        ->and($checker->isBreached('123456789012'))->toBeTrue()
        ->and($checker->isBreached('P@ssw0rd1234'))->toBeTrue()
        ->and($checker->isBreached('Riyadh@123456'))->toBeTrue()
        ->and($checker->isBreached('كلمةالمرور123'))->toBeTrue();
});
