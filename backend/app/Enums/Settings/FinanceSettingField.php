<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\PermissionKey;

enum FinanceSettingField: string
{
    case AccreditedPrice = 'accredited_price';
    case BagPrice = 'bag_price';
    case VatRate = 'vat_rate';
    case OrderValidityMinutes = 'order_validity_minutes';
    case VatRegistrationNumber = 'vat_registration_number';

    public static function priceOf(PaymentPurpose $purpose): self
    {
        return match ($purpose) {
            PaymentPurpose::Accredited => self::AccreditedPrice,
            PaymentPurpose::Bag => self::BagPrice,
        };
    }

    public function settingKey(): SettingKey
    {
        return match ($this) {
            self::AccreditedPrice => SettingKey::AccreditedPrice,
            self::BagPrice => SettingKey::BagPrice,
            self::VatRate => SettingKey::VatRate,
            self::OrderValidityMinutes => SettingKey::PaymentOrderValidityMinutes,
            self::VatRegistrationNumber => SettingKey::VatRegistrationNumber,
        };
    }

    public function permission(): PermissionKey
    {
        return match ($this) {
            self::BagPrice => PermissionKey::BagAdminManage,
            self::VatRegistrationNumber => PermissionKey::SettingsPaymentManage,
            default => PermissionKey::SettingsAssessmentManage,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
