<?php

declare(strict_types=1);

use App\Enums\Platform\DestructiveDatabaseCommand;
use App\Services\Platform\DestructiveCommandBaseline;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\GuardMarkerSeeder;

const GUARD_DEV_PROBE = 'guard_probe_dev';

const GUARD_TEST_PROBE = 'guard_probe_test';

function sqliteProbe(string $connection, string $databaseFile): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'moashir-guard-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $path = $directory.DIRECTORY_SEPARATOR.$databaseFile;
    touch($path);

    config()->set('database.connections.'.$connection, ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true]);
    Schema::connection($connection)->create(GuardMarkerSeeder::TABLE, static function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
    });

    test()->beforeApplicationDestroyed(static function () use ($connection, $directory): void {
        DB::purge($connection);
        array_map(unlink(...), glob($directory.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($directory);
    });

    return $path;
}

function markerTableSurvives(string $connection): bool
{
    return Schema::connection($connection)->hasTable(GuardMarkerSeeder::TABLE);
}

function markerRows(string $connection): int
{
    return DB::connection($connection)->table(GuardMarkerSeeder::TABLE)->count();
}

function bootArtisanAsCommandLineIn(string $environment): void
{
    app()->instance('env', $environment);
    app(DestructiveCommandBaseline::class)->apply();

    $kernel = app(ConsoleKernelContract::class);
    expect($kernel)->toBeInstanceOf(ConsoleKernel::class);

    $kernel->rerouteSymfonyCommandEvents();
    $kernel->setArtisan(null);
}

it('refuses every destructive command on a non-test database from the local command line', function (string $command): void {
    sqliteProbe(GUARD_DEV_PROBE, 'moashir');
    bootArtisanAsCommandLineIn('local');

    $this->artisan($command, ['--database' => GUARD_DEV_PROBE])
        ->expectsOutputToContain('is not a test database. To run this command against it on purpose in the local environment, repeat it with --confirm-database=')
        ->assertFailed();

    expect(markerTableSurvives(GUARD_DEV_PROBE))->toBeTrue();
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe']);

it('refuses a confirmation that does not name the target database exactly', function (): void {
    sqliteProbe(GUARD_DEV_PROBE, 'moashir');
    bootArtisanAsCommandLineIn('local');

    $this->artisan('db:wipe', ['--database' => GUARD_DEV_PROBE, '--confirm-database' => 'moashir'])
        ->expectsOutputToContain('is not a test database')
        ->assertFailed();

    expect(markerTableSurvives(GUARD_DEV_PROBE))->toBeTrue();
});

it('lets a developer destroy the local database on purpose with the exact confirmation', function (): void {
    $database = sqliteProbe(GUARD_DEV_PROBE, 'moashir');
    bootArtisanAsCommandLineIn('local');

    $this->artisan('db:wipe', ['--database' => GUARD_DEV_PROBE, '--confirm-database' => $database])->assertSuccessful();

    expect(markerTableSurvives(GUARD_DEV_PROBE))->toBeFalse();
});

it('allows destructive commands on a test database from the local command line', function (): void {
    sqliteProbe(GUARD_TEST_PROBE, 'moashir_testing');
    bootArtisanAsCommandLineIn('local');

    $this->artisan('db:wipe', ['--database' => GUARD_TEST_PROBE])->assertSuccessful();

    expect(markerTableSurvives(GUARD_TEST_PROBE))->toBeFalse();
});

it('always refuses destructive commands in protected environments', function (string $environment, string $command): void {
    $database = sqliteProbe(GUARD_TEST_PROBE, 'moashir_testing');
    bootArtisanAsCommandLineIn($environment);

    $this->artisan($command, ['--database' => GUARD_TEST_PROBE, '--confirm-database' => $database, '--force' => true])
        ->expectsOutputToContain("never run in the [{$environment}] environment")
        ->assertFailed();

    expect(markerTableSurvives(GUARD_TEST_PROBE))->toBeTrue();
})->with(['staging', 'production', 'preview'])->with(['db:wipe', 'migrate:fresh']);

it('always refuses seeding in protected environments, forced or not', function (string $environment, array $options): void {
    sqliteProbe(GUARD_TEST_PROBE, 'moashir_testing');
    bootArtisanAsCommandLineIn($environment);

    $this->artisan('db:seed', ['--database' => GUARD_TEST_PROBE, '--class' => GuardMarkerSeeder::class, ...$options])
        ->expectsOutputToContain('prohibited')
        ->assertFailed();

    expect(markerRows(GUARD_TEST_PROBE))->toBe(0);
})->with(['staging', 'production'])->with([
    'plain' => [[]],
    'forced' => [['--force' => true]],
]);

it('refuses forced seeding of a non-test database from the local command line', function (): void {
    sqliteProbe(GUARD_DEV_PROBE, 'moashir');
    bootArtisanAsCommandLineIn('local');

    $this->artisan('db:seed', ['--database' => GUARD_DEV_PROBE, '--class' => GuardMarkerSeeder::class, '--force' => true])
        ->expectsOutputToContain('is not a test database')
        ->assertFailed();

    expect(markerRows(GUARD_DEV_PROBE))->toBe(0);
});

it('allows forced seeding of the local database with the exact confirmation', function (): void {
    $database = sqliteProbe(GUARD_DEV_PROBE, 'moashir');
    bootArtisanAsCommandLineIn('local');

    $this->artisan('db:seed', ['--database' => GUARD_DEV_PROBE, '--class' => GuardMarkerSeeder::class, '--force' => true, '--confirm-database' => $database])
        ->assertSuccessful();

    expect(markerRows(GUARD_DEV_PROBE))->toBe(1);
});

it('keeps plain local seeding available because seeders only add data', function (): void {
    sqliteProbe(GUARD_DEV_PROBE, 'moashir');
    bootArtisanAsCommandLineIn('local');

    $this->artisan('db:seed', ['--database' => GUARD_DEV_PROBE, '--class' => GuardMarkerSeeder::class])->assertSuccessful();

    expect(markerRows(GUARD_DEV_PROBE))->toBe(1);
});

it('allows the test database in the testing environment', function (): void {
    sqliteProbe(GUARD_TEST_PROBE, 'moashir_testing');
    app(DestructiveCommandBaseline::class)->apply();

    $this->artisan('db:wipe', ['--database' => GUARD_TEST_PROBE])->assertSuccessful();

    expect(markerTableSurvives(GUARD_TEST_PROBE))->toBeFalse();
});

it('locks every destructive command in the testing environment while any connection points at a non-test database', function (): void {
    sqliteProbe(GUARD_TEST_PROBE, 'moashir_testing');
    sqliteProbe(GUARD_DEV_PROBE, 'moashir');
    app(DestructiveCommandBaseline::class)->apply();

    $this->artisan('db:wipe', ['--database' => GUARD_DEV_PROBE])->expectsOutputToContain('prohibited')->assertFailed();
    $this->artisan('db:wipe', ['--database' => GUARD_TEST_PROBE])->expectsOutputToContain('prohibited')->assertFailed();

    expect(markerTableSurvives(GUARD_DEV_PROBE))->toBeTrue()
        ->and(markerTableSurvives(GUARD_TEST_PROBE))->toBeTrue();
});

it('offers the confirmation option on every guarded command', function (): void {
    $commands = Artisan::all();

    foreach (DestructiveDatabaseCommand::cases() as $command) {
        expect($commands[$command->value]->getDefinition()->hasOption(DestructiveDatabaseCommand::CONFIRMATION_OPTION))->toBeTrue();
    }
});

it('stops the test suite before refreshing a database that is not a test database', function (string $database): void {
    $current = config('database.connections.pgsql.database');
    config()->set('database.connections.pgsql.database', $database);

    try {
        expect(fn () => $this->beforeRefreshingDatabase())->toThrow(AssertionFailedError::class, "[{$database}] is not a test database");
    } finally {
        config()->set('database.connections.pgsql.database', $current);
    }
})->with([
    'development database' => ['moashir'],
    'parallel copy of the development database' => ['moashir_test_1'],
]);

it('accepts the test database of this process in sequential and parallel runs', function (): void {
    $current = config('database.connections.pgsql.database');

    expect($current)->toBe(suiteTestDatabase())
        ->and($current)->toStartWith(TEST_DATABASE);

    expect(fn () => $this->beforeRefreshingDatabase())->not->toThrow(AssertionFailedError::class);
});

it('runs the suite on the test environment file and the test database', function (): void {
    $values = Dotenv::parse((string) file_get_contents(base_path('.env.testing')));

    expect(app()->environmentFile())->toBe('.env.testing')
        ->and($values['APP_ENV'] ?? null)->toBe('testing')
        ->and($values['DB_DATABASE'] ?? null)->toBe('moashir_testing')
        ->and($values['APP_KEY'] ?? null)->toBe('')
        ->and($values['DB_PASSWORD'] ?? null)->toBe('')
        ->and(config('database.connections.pgsql.database'))->toBe(suiteTestDatabase())
        ->and(config('app.key'))->toStartWith('base64:');
});
