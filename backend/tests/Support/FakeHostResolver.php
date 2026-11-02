<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Integrations\HostResolver;

final class FakeHostResolver implements HostResolver
{
    /**
     * @param  array<string, list<string>>  $addresses
     * @param  array<string, list<string>>  $textRecords
     */
    public function __construct(private array $addresses = [], private array $textRecords = []) {}

    public function addressesOf(string $host): array
    {
        return $this->addresses[mb_strtolower($host)] ?? [];
    }

    public function textRecordsOf(string $name): array
    {
        return $this->textRecords[mb_strtolower($name)] ?? [];
    }
}
