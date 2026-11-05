<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\HostResolver;
use App\Support\Integrations\IpAddressPolicy;

final readonly class SmtpHostGuard
{
    public const array ALLOWED_PORTS = [25, 465, 587, 2465, 2525, 2587];

    public function __construct(private HostResolver $resolver) {}

    public function isAllowedPort(int $port): bool
    {
        return in_array($port, self::ALLOWED_PORTS, true);
    }

    public function publicAddressOf(string $host): ?string
    {
        if (! self::isHostname($host)) {
            return null;
        }

        $addresses = $this->resolver->addressesOf($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! IpAddressPolicy::isPublic($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    public static function isHostname(string $host): bool
    {
        if (IpAddressPolicy::isLiteral($host)) {
            return false;
        }

        return preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/i', $host) === 1;
    }
}
