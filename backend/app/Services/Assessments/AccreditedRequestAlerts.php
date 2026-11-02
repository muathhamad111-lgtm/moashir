<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Finance\Payment;
use App\Services\Entities\EntityNotifier;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class AccreditedRequestAlerts
{
    private const string KEY = 'notifications.assessments.accredited_opening_failed';

    public function __construct(private EntityNotifier $notifier, private Cache $cache, private Config $config) {}

    public function openingFailed(Payment $payment): void
    {
        $minutes = max(1, $this->config->integer('moashir.integrations.alert_cooldown_minutes'));

        if (! $this->cache->add('assessments:alert:opening-failed:'.$payment->id, true, $minutes * 60)) {
            return;
        }

        $this->notifier->toRole(RoleKey::SystemAdmin, new EntityNotice(
            NotificationCode::RequestProcessingFailed,
            LocalizedText::fromTranslation(self::KEY.'.title'),
            LocalizedText::fromTranslationWith(self::KEY.'.body', ['reference' => $payment->reference]),
            null,
            false,
            true,
        ), null);
    }
}
