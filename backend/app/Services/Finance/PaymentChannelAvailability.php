<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Settings\SettingKey;
use App\Models\Finance\BankAccount;
use App\Services\Integrations\IntegrationCredentialsResolver;
use App\Services\Settings\SettingsStore;
use App\Support\StringList;

final readonly class PaymentChannelAvailability
{
    public function __construct(private SettingsStore $settings, private IntegrationCredentialsResolver $credentials) {}

    /**
     * @return list<PaymentChannel>
     */
    public function enabled(): array
    {
        return array_values(array_filter(array_map(
            PaymentChannel::tryFrom(...),
            StringList::from($this->settings->get(SettingKey::PaymentChannels)),
        )));
    }

    public function isEnabled(PaymentChannel $channel): bool
    {
        return in_array($channel, $this->enabled(), true);
    }

    /**
     * @return list<PaymentChannel>
     */
    public function available(): array
    {
        return array_values(array_filter($this->enabled(), $this->isReady(...)));
    }

    public function isAvailable(PaymentChannel $channel): bool
    {
        return $this->isEnabled($channel) && $this->isReady($channel);
    }

    public function isReady(PaymentChannel $channel): bool
    {
        return match ($channel) {
            PaymentChannel::Electronic => $this->isGatewayReady(),
            PaymentChannel::BankTransfer => $this->selectableBankAccountsCount() > 0,
        };
    }

    public function isGatewayReady(): bool
    {
        return $this->credentials->resolve(IntegrationKind::Payment)->isConfigured();
    }

    public function selectableBankAccountsCount(): int
    {
        return BankAccount::query()->selectable()->count();
    }
}
