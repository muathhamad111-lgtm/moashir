<?php

declare(strict_types=1);

namespace App\Integrations\Smtp;

use App\Contracts\Integrations\HostResolver;
use ErrorException;

final class SystemHostResolver implements HostResolver
{
    public function addressesOf(string $host): array
    {
        $addresses = [];

        foreach ($this->records($host, DNS_A | DNS_AAAA) as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    public function textRecordsOf(string $name): array
    {
        $texts = [];

        foreach ($this->records($name, DNS_TXT) as $record) {
            if (is_string($record['txt'] ?? null)) {
                $texts[] = $record['txt'];
            }
        }

        return $texts;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function records(string $name, int $type): array
    {
        try {
            $records = dns_get_record($name, $type);
        } catch (ErrorException) {
            return [];
        }

        return is_array($records) ? array_values(array_filter($records, is_array(...))) : [];
    }
}
