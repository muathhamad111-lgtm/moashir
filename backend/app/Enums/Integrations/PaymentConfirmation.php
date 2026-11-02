<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

use App\Enums\Settings\SettingKey;

enum PaymentConfirmation: string
{
    case ApplePayDomain = 'apple_pay_domain';
    case StcPayActivation = 'stcpay_activation';

    public function settingKey(): SettingKey
    {
        return match ($this) {
            self::ApplePayDomain => SettingKey::PaymentApplePayDomainConfirmation,
            self::StcPayActivation => SettingKey::PaymentStcPayActivationConfirmation,
        };
    }
}
