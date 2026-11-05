<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\Integrations\HostResolver;
use App\Data\Platform\DestructiveCommandTarget;
use App\Services\Platform\ConnectionDatabaseNames;
use App\Services\Platform\DestructiveCommandGuard;
use App\Support\ScalarValue;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeHostResolver;
use Tests\Support\TableByTableSchemaWipe;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        migrateDatabases as migrateFreshDatabase;
    }

    protected bool $seed = true;

    protected string $seeder = PermissionCatalogSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('local');
        $this->app->instance(HostResolver::class, new FakeHostResolver);

        $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/', 'Accept-Language' => 'ar']);
    }

    protected function migrateDatabases(): void
    {
        $connection = $this->app->make('db')->connection();
        assert($connection instanceof Connection);

        new TableByTableSchemaWipe($connection)->handle();

        $this->migrateFreshDatabase();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $target = new DestructiveCommandTarget(
            ScalarValue::string($this->app->environment()),
            $this->app->make(ConnectionDatabaseNames::class)->of(null),
        );
        $verdict = $this->app->make(DestructiveCommandGuard::class)->verdict($target);

        if (! $verdict->isAllowed()) {
            $this->fail('The test suite refuses to refresh this database. '.$verdict->explain($target));
        }
    }
}
