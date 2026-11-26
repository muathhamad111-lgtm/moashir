<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Data\Platform\ReadinessViewer;
use App\Enums\Identity\Ability;
use App\Models\Identity\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Symfony\Component\HttpFoundation\IpUtils;

final readonly class ReadinessDetailsAccess
{
    public function __construct(private Repository $config, private Gate $gate) {}

    public function allows(ReadinessViewer $viewer): bool
    {
        return $this->isAllowListed($viewer->ip)
            || ($viewer->user instanceof User && $this->gate->forUser($viewer->user)->allows(Ability::ViewReadinessDetails->value));
    }

    private function isAllowListed(?string $ip): bool
    {
        $allowed = $this->config->get('moashir.security.health_allowed_ips');
        $ranges = is_array($allowed) ? array_values(array_filter($allowed, is_string(...))) : [];

        return $ip !== null && $ranges !== [] && IpUtils::checkIp($ip, $ranges);
    }
}
