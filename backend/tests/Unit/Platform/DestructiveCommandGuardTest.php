<?php

declare(strict_types=1);

use App\Data\Platform\DestructiveCommandTarget;
use App\Enums\Platform\DestructiveCommandVerdict;
use App\Services\Platform\ConnectionDatabaseNames;
use App\Services\Platform\DestructiveCommandGuard;

it('decides destructive commands by environment, database and confirmation', function (string $environment, ?string $database, ?string $confirmation, DestructiveCommandVerdict $expected): void {
    $verdict = app(DestructiveCommandGuard::class)->verdict(new DestructiveCommandTarget($environment, $database, $confirmation));

    expect($verdict)->toBe($expected);
})->with([
    'test database while testing' => ['testing', 'moashir_testing', null, DestructiveCommandVerdict::Allowed],
    'parallel test database while testing' => ['testing', 'moashir_testing_test_3', null, DestructiveCommandVerdict::Allowed],
    'parallel test database with a two-digit token' => ['testing', 'moashir_testing_test_12', null, DestructiveCommandVerdict::Allowed],
    'parallel copy of the dev database while testing' => ['testing', 'moashir_test_3', null, DestructiveCommandVerdict::NotTestDatabase],
    'parallel copy of the dev database locally' => ['local', 'moashir_test_3', null, DestructiveCommandVerdict::ConfirmationRequired],
    'token without the parallel infix' => ['testing', 'moashir_testing_3', null, DestructiveCommandVerdict::NotTestDatabase],
    'parallel suffix without a token' => ['testing', 'moashir_testing_test_', null, DestructiveCommandVerdict::NotTestDatabase],
    'dev database while testing' => ['testing', 'moashir', null, DestructiveCommandVerdict::NotTestDatabase],
    'dev database while testing even when confirmed' => ['testing', 'moashir', 'moashir', DestructiveCommandVerdict::NotTestDatabase],
    'test database locally' => ['local', 'moashir_testing', null, DestructiveCommandVerdict::Allowed],
    'dev database locally' => ['local', 'moashir', null, DestructiveCommandVerdict::ConfirmationRequired],
    'dev database locally with another name confirmed' => ['local', 'moashir', 'moashir_testing', DestructiveCommandVerdict::ConfirmationRequired],
    'dev database locally with its name confirmed' => ['local', 'moashir', 'moashir', DestructiveCommandVerdict::Allowed],
    'look-alike test name' => ['local', 'moashir_testing_backup', null, DestructiveCommandVerdict::ConfirmationRequired],
    'testing as a prefix only' => ['testing', 'testing_moashir', null, DestructiveCommandVerdict::NotTestDatabase],
    'unknown database locally' => ['local', null, null, DestructiveCommandVerdict::ConfirmationRequired],
    'staging' => ['staging', 'moashir_testing', 'moashir_testing', DestructiveCommandVerdict::ProtectedEnvironment],
    'production' => ['production', 'moashir_testing', 'moashir_testing', DestructiveCommandVerdict::ProtectedEnvironment],
    'unrecognised environment' => ['preview', 'moashir_testing', null, DestructiveCommandVerdict::ProtectedEnvironment],
]);

it('allows seeding only in the local and testing environments', function (string $environment, DestructiveCommandVerdict $expected): void {
    expect(app(DestructiveCommandGuard::class)->seedingVerdict($environment))->toBe($expected);
})->with([
    'local' => ['local', DestructiveCommandVerdict::Allowed],
    'testing' => ['testing', DestructiveCommandVerdict::Allowed],
    'staging' => ['staging', DestructiveCommandVerdict::ProtectedEnvironment],
    'production' => ['production', DestructiveCommandVerdict::ProtectedEnvironment],
]);

it('explains each refusal with the database and the way forward', function (): void {
    $target = new DestructiveCommandTarget('local', 'moashir');

    expect(DestructiveCommandVerdict::ConfirmationRequired->explain($target))->toContain('[moashir] is not a test database', '--confirm-database=moashir')
        ->and(DestructiveCommandVerdict::NotTestDatabase->explain($target))->toContain('[moashir] is not a test database', '_testing')
        ->and(DestructiveCommandVerdict::ProtectedEnvironment->explain(new DestructiveCommandTarget('production', 'moashir')))->toContain('[production] environment');
});

it('never treats an empty confirmation as confirming an unknown database', function (): void {
    expect(new DestructiveCommandTarget('local', null, '')->isConfirmed())->toBeFalse()
        ->and(new DestructiveCommandTarget('local', null)->isConfirmed())->toBeFalse();
});

it('reads database names from the connection configuration without connecting', function (): void {
    config()->set('database.connections.from_url', ['driver' => 'pgsql', 'url' => 'pgsql://reader@db.internal.example.sa:5432/reporting_testing', 'database' => 'ignored']);
    config()->set('database.connections.unnamed', ['driver' => 'pgsql', 'database' => '']);

    $names = app(ConnectionDatabaseNames::class);

    expect($names->of(null))->toBe(suiteTestDatabase())
        ->and($names->of('from_url'))->toBe('reporting_testing')
        ->and($names->of('unnamed'))->toBeNull()
        ->and($names->of('not_configured'))->toBeNull()
        ->and($names->ofEveryConnection())->toContain(suiteTestDatabase(), 'reporting_testing', null);
});
