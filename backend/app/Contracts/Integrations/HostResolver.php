<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

interface HostResolver
{
    /**
     * @return list<string>
     */
    public function addressesOf(string $host): array;

    /**
     * @return list<string>
     */
    public function textRecordsOf(string $name): array;
}
