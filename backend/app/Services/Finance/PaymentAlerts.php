<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Finance\Payment;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;
use App\Support\Integrations\IntegrationNotices;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class PaymentAlerts
{
    private const array FINANCE_ROLES = [RoleKey::FinanceOfficer, RoleKey::SystemAdmin];

    public function __construct(private EntityNotifier $notifier, private Cache $cache, private Config $config) {}

    public function refundRequired(Payment $payment): void
    {
        $this->toFinance(FinanceNotices::forPayment(NotificationCode::PaymentRefundRequired, FinanceNotices::FOR_STAFF, $payment));
    }

    public function providerRefundReported(Payment $payment): void
    {
        if ($this->firstTime('provider-refund:'.$payment->id)) {
            $this->toFinance(FinanceNotices::forPayment(NotificationCode::ProviderRefundReported, FinanceNotices::FOR_STAFF, $payment));
        }
    }

    public function serviceIssue(string $issue): void
    {
        if ($this->firstTime('issue:'.$issue)) {
            $notice = IntegrationNotices::for(NotificationCode::ExternalServiceDown);
            $key = 'notifications.payments.issues.'.$issue;

            $this->notifier->toRole(RoleKey::SystemAdmin, new EntityNotice(
                $notice->code,
                LocalizedText::fromTranslation($key.'.title'),
                LocalizedText::fromTranslation($key.'.body'),
                $notice->link,
                $notice->viaMail,
                $notice->viaInApp,
            ), null);
        }
    }

    private function toFinance(EntityNotice $notice): void
    {
        foreach (self::FINANCE_ROLES as $role) {
            $this->notifier->toRole($role, $notice, null);
        }
    }

    private function firstTime(string $scope): bool
    {
        $minutes = max(1, $this->config->integer('moashir.integrations.alert_cooldown_minutes'));

        return $this->cache->add('finance:alert:'.$scope, true, $minutes * 60);
    }
}
