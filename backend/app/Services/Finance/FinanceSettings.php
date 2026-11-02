<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use App\Support\StringList;
use App\Support\ValueObjects\VatRate;
use Illuminate\Contracts\Config\Repository;

final readonly class FinanceSettings
{
    public function __construct(private SettingsStore $settings, private Repository $config) {}

    public function vatRate(): VatRate
    {
        return new VatRate(ScalarValue::string($this->settings->get(SettingKey::VatRate), ScalarValue::string(SettingKey::VatRate->defaultValue())));
    }

    public function orderValidityMinutes(): int
    {
        $minutes = ScalarValue::int($this->settings->get(SettingKey::PaymentOrderValidityMinutes), ScalarValue::int(SettingKey::PaymentOrderValidityMinutes->defaultValue()));
        $limits = $this->orderValidityLimits();

        return max($limits['min'], min($limits['max'], $minutes));
    }

    /**
     * @return array{min: int, max: int}
     */
    public function orderValidityLimits(): array
    {
        return [
            'min' => $this->config->integer('moashir.finance.order_validity_minutes.min'),
            'max' => $this->config->integer('moashir.finance.order_validity_minutes.max'),
        ];
    }

    public function abandonedCartAfterHours(): int
    {
        return max(1, ScalarValue::int($this->settings->get(SettingKey::AbandonedCartAfterHours), ScalarValue::int(SettingKey::AbandonedCartAfterHours->defaultValue())));
    }

    /**
     * @return list<string>
     */
    public function linkedPaths(): array
    {
        return StringList::from($this->settings->get(SettingKey::PaymentLinkedPaths));
    }

    public function pendingGraceMinutes(): int
    {
        return $this->config->integer('moashir.finance.pending_grace_minutes');
    }

    public function reconcileAfterMinutes(): int
    {
        return $this->config->integer('moashir.finance.reconcile_after_minutes');
    }

    public function webhookRedispatchAfterMinutes(): int
    {
        return $this->config->integer('moashir.finance.webhook_redispatch_after_minutes');
    }

    public function exportSyncMaxRows(): int
    {
        return $this->config->integer('moashir.finance.export_sync_max_rows');
    }
}
