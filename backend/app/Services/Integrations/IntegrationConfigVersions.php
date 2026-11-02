<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\Integrations\IntegrationKind;
use App\Models\Integrations\IntegrationCredential;
use App\Support\ScalarValue;
use Illuminate\Contracts\Cache\Repository;

final readonly class IntegrationConfigVersions
{
    public function __construct(private Repository $cache) {}

    public function current(IntegrationKind $kind): int
    {
        return ScalarValue::int($this->cache->rememberForever(
            $this->key($kind),
            static fn (): int => ScalarValue::int(IntegrationCredential::query()->ofKind($kind)->sum('config_version')),
        ));
    }

    public function forget(IntegrationKind $kind): void
    {
        $this->cache->forget($this->key($kind));
    }

    private function key(IntegrationKind $kind): string
    {
        return 'integrations:config_version:'.$kind->value;
    }
}
