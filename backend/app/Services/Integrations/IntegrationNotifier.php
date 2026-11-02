<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Services\Entities\EntityNotifier;
use App\Support\Integrations\IntegrationNotices;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class IntegrationNotifier
{
    public function __construct(private EntityNotifier $notifier, private Cache $cache, private Config $config) {}

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public function notify(NotificationCode $code, ?int $actorId, array $replacements = []): void
    {
        $this->notifier->toRole(RoleKey::SystemAdmin, IntegrationNotices::for($code, $replacements), $actorId);
    }

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public function notifyOnce(NotificationCode $code, string $scope, array $replacements = []): void
    {
        $minutes = max(1, $this->config->integer('moashir.integrations.alert_cooldown_minutes'));

        if ($this->cache->add('integrations:alert:'.$code->value.':'.$scope, true, $minutes * 60)) {
            $this->notify($code, null, $replacements);
        }
    }
}
